<?php
namespace App\Services\Payroll;

use App\Models\{Employee, PayrollRun, Payslip, EmployeeSalary, SalaryComponent, AttendanceLog, LeaveRequest, PayrollManualDays, PublicHoliday, HolidayPayApproval, HolidayWork};
use App\Exceptions\ImportedPayrollRunException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PayrollService
{
    /**
     * URA's resident monthly PAYE bands: [threshold, ceiling, rate, base].
     *
     * The thresholds are the round figures URA publishes — 235,000, 335,000,
     * 410,000 — and not one shilling above them. The band is entered when pay is
     * strictly greater than the threshold, and the rate applies to the excess
     * *over* it, which is what "30% of the excess over 410,000" means.
     *
     * They were written here as 235001, 335001 and 410001, which measured each
     * excess from one shilling too high and under-charged every band by that
     * shilling times its rate. The effect on any one payslip is under a shilling
     * and rounds away in most cases, so nothing ever looked wrong — but a tax
     * calculation that is knowingly a shilling out is not one to leave in place,
     * and the fix costs nothing.
     *
     * The top band is the two published rules combined: 30% continues above ten
     * million and a further 10% is added, so 2,902,000 (the tax at exactly ten
     * million) plus 40% of everything beyond it.
     */
    private array $taxBrackets = [
        [0,        235000,      0.00, 0],
        [235000,   335000,      0.10, 0],
        [335000,   410000,      0.20, 10000],
        [410000,   10000000,    0.30, 25000],
        [10000000, PHP_INT_MAX, 0.40, 2902000],
    ];

    public function processRun(PayrollRun $run): int
    {
        // An imported run holds payslips this engine did not produce and cannot
        // reproduce. Re-processing would silently replace what was really paid
        // with figures derived from today's salary records, so it is refused
        // here as well as at every controller — this is the floor, and any new
        // caller gets the protection without having to remember it.
        if ($run->isImported()) {
            throw new ImportedPayrollRunException(
                'Payroll run '.$run->id.' was imported on '.$run->imported_at->toDateString()
                .' and cannot be re-processed. Create a new run instead.'
            );
        }

        // The one definition of who may be paid. See Employee::scopePayrollEligible.
        $query = Employee::payrollEligible();

        if ($run->client_id) {
            $query->whereHas('clients', fn($q) => $q->where('clients.id', $run->client_id));
        }

        // Who is actually in this run.
        //
        // A payroll_manual_days row IS the selection: the account manager enters
        // days for the people who worked, and that is the same list. Before
        // this, every eligible employee was processed whichever way, so a client
        // with 439 staff and six people on site produced 433 payslips of zero —
        // and all 433 went to HR to be reviewed. Zero is not a fact about
        // somebody's pay, it is the absence of one.
        //
        // When a run has no rows at all, nothing is selected and the old
        // behaviour stands: everyone eligible is processed. That keeps the
        // clients who run off attendance rather than manual days working exactly
        // as they did.
        // Days of zero is not a selection. The spreadsheet import writes a row
        // for everybody on the client, so on Roofings run 31 that was 433 rows of
        // which only 20 carried any days — treating a row as a selection would
        // still have paid 433 people and produced 413 payslips of zero.
        //
        // Somebody who worked no days is not paid and needs no payslip. If a
        // run genuinely has to record a zero, it is a leave or absence record,
        // not a payslip.
        $selected = PayrollManualDays::where('payroll_run_id', $run->id)
            ->where('days_worked', '>', 0)
            ->pluck('employee_id');

        if ($selected->isNotEmpty()) {
            $query->whereIn('id', $selected);
        }

        $employees = $query->get();

        foreach ($employees as $employee) {
            $this->processEmployee($employee, $run);
        }

        $run->update(['status' => 'processed', 'processed_by' => auth()->id(), 'processed_at' => now()]);
        return $employees->count();
    }

    public function processEmployee(Employee $employee, PayrollRun $run): Payslip
    {
        $salary = EmployeeSalary::where('employee_id', $employee->id)
            ->where('is_current', true)->latest()->first();

        $salaryType = $salary ? ($salary->salary_type ?? 'monthly') : 'monthly';
        $rateValue  = $salary ? (float) $salary->basic_salary : 0;

        // Client-level settings
        $client     = $run->client;
        $grossUpPaye = $client?->gross_up_paye ?? false;
        $gpaRate    = (float) ($client?->gpa_wmc_rate ?? 0);   // e.g. 2.0 = 2%
        $billMult   = (float) ($client?->billing_rate_multiplier ?? 1.0);

        $start = Carbon::create($run->year, $run->month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $totalCalendarDays = $start->copy()->daysInMonth;
        $totalWorkingDays  = $this->countWorkingDays($start, $end); // Mon–Fri count (for non-monthly types)

        // ── Attendance / Manual Days ────────────────────────────────
        $manualDays = PayrollManualDays::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)->first();

        $isCasualRate = in_array($salaryType, ['daily', 'hourly'], true);

        if ($manualDays) {
            // Manual upload: days_worked is what HR counted, including any public
            // holiday the person actually worked (that day is a worked day).
            $workedDays = (int) $manualDays->days_worked;
            $absentDays = max(0, $totalCalendarDays - $workedDays);
            $holidayBonusDays = 0;
        } else {
            $presentDays = AttendanceLog::where('employee_id', $employee->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'present')->count();

            $absentDays = AttendanceLog::where('employee_id', $employee->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'absent')->count();

            // Monthly staff keep the old treatment: public holidays count toward
            // their days so the month comes out whole. Casual rates are handled
            // by the approval-gated block below instead.
            $holidayBonusDays = $isCasualRate ? 0 : PublicHoliday::countInRange(
                $start->toDateString(), $end->toDateString()
            );

            $workedDays = $presentDays + $holidayBonusDays;
        }

        // ── Public holiday pay (daily / hourly staff only) ───────────────
        //
        // One extra day-unit is added per *approved* holiday, which lands both
        // rules on the same number:
        //   approved + did not work  -> the day was never counted, +1 = 1x rate
        //   approved + worked        -> the day is already counted, +1 = 2x rate
        //   not approved + worked    -> already counted, +0 = 1x rate (flagged)
        //   not approved + not worked-> never counted, +0 = nothing
        //
        // Monthly staff are deliberately excluded: their salary already covers
        // the holiday, so working one adds nothing.
        $holidayPay = ['extra_days' => 0, 'worked' => [], 'unapproved_worked' => []];
        if ($isCasualRate) {
            $holidayPay = $this->holidayPayAdjustment($employee, $run, $start, $end);
            $workedDays += $holidayPay['extra_days'];
        }

        // ── Overtime ────────────────────────────────────────────────
        // Only hours HR or an admin has explicitly approved are paid. The raw
        // overtime_hours written at clock-out is what the clock saw, not what
        // was authorised — paying it directly meant overtime went out at 1.5x
        // with nobody signing it off.
        $totalOvertimeHours = (float) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('overtime_status', 'approved')
            ->sum('approved_overtime_hours');

        // ── Basic salary computation ────────────────────────────────
        $basic         = 0;
        $overtimePay   = 0;
        $prorateFactor = 1.0;
        $annualSalary  = 0;
        $dailyRate     = 0;

        if ($salaryType === 'daily') {
            $basic       = round($rateValue * $workedDays, 0);
            $hourlyRate  = $rateValue / 8;
            $overtimePay = round($totalOvertimeHours * $hourlyRate * 1.5, 0);

        } elseif ($salaryType === 'hourly') {
            $basic       = round($rateValue * ($workedDays * 8), 0);
            $overtimePay = round($totalOvertimeHours * $rateValue * 1.5, 0);

        } else {
            // ── Monthly: Annual ÷ 365 × days_worked (pro-rata) ──────
            // basic_salary is the monthly amount; annual = monthly × 12
            $annualSalary  = $rateValue * 12;
            $dailyRate     = $annualSalary / 365;

            // If employee joined or left mid-month, adjust worked days proportionally
            $hireDate = $employee->hire_date;
            $endDate  = $employee->end_date;

            if ($hireDate && $hireDate->between($start, $end)) {
                // Joined mid-month: count only calendar days from hire date to month-end
                $calDaysFromHire = $hireDate->diffInDays($end) + 1;
                // Also get public holidays only from hire date onwards
                $holidaysFromHire = PublicHoliday::countInRange(
                    $hireDate->toDateString(), $end->toDateString()
                );
                $workedDays    = $manualDays ? $workedDays : min($workedDays, $calDaysFromHire);
                $prorateFactor = $totalCalendarDays > 0 ? round($calDaysFromHire / $totalCalendarDays, 4) : 1;
            } elseif ($endDate && $endDate->between($start, $end)) {
                // Left mid-month: count only calendar days from month-start to exit
                $calDaysToExit = $start->diffInDays($endDate) + 1;
                $workedDays    = $manualDays ? $workedDays : min($workedDays, $calDaysToExit);
                $prorateFactor = $totalCalendarDays > 0 ? round($calDaysToExit / $totalCalendarDays, 4) : 1;
            }

            $basic       = round($dailyRate * $workedDays, 0);
            $overtimePay = round($totalOvertimeHours * ($dailyRate / 8) * 1.5, 0);
        }

        // ── Gross-Up: Employee takes home exactly basic (rate × days) ──
        // If grossUpPaye=true, we gross up so that: gross - PAYE - NSSF5% = basic
        // Solved iteratively: gross = basic / (1 - nssf_rate - effective_paye_rate)
        if ($grossUpPaye && $basic > 0 && $salaryType !== 'daily' && $salaryType !== 'hourly') {
            // Monthly / gross-up only for daily/hourly casuals
        }
        $grossedUp = 0;
        if ($grossUpPaye && $basic > 0) {
            $grossedUp = $this->grossUpAmount($basic);
            $basic = $grossedUp;  // replace basic with grossed-up amount
        }

        // ── GPA/WMC allowance (employer pays separately — added to client billing, NOT to employee deductions) ──
        $gpaMwcAmount = $gpaRate > 0 ? round($basic * $gpaRate / 100, 0) : 0;

        // ── Salary Components ───────────────────────────────────────
        $allowances   = 0;
        $deductions   = 0;
        $details      = [];
        $employeeNssf = 0;
        $employerNssf = 0;

        if ($salary && $salary->components) {
            foreach ($salary->components as $comp) {
                $component = SalaryComponent::find($comp['component_id'] ?? null);
                if (!$component || !$component->is_active) continue;

                $amount = $component->is_fixed
                    ? (float) ($comp['amount'] ?? $component->amount)
                    : round($basic * ((float)($comp['percentage'] ?? $component->percentage)) / 100, 0);

                // The employer's 10% NSSF share is a company cost: it is shown on
                // the payslip and remitted to NSSF, but it is neither added to the
                // employee's gross nor deducted from their net pay.
                if ($component->code === 'NSSF_CO') {
                    $employerNssf = $amount;
                    $details[] = [
                        'name'    => $component->name,
                        'code'    => 'NSSF_CO',
                        'type'    => 'employer_cost',
                        'amount'  => $amount,
                        'taxable' => false,
                    ];
                    continue;
                }

                $details[] = [
                    'name'    => $component->name,
                    'code'    => $component->code,
                    'type'    => $component->type,
                    'amount'  => $amount,
                    'taxable' => (bool)$component->is_taxable,
                ];

                if ($component->code === 'NSSF_EMP') $employeeNssf = $amount;

                if ($component->type === 'allowance') $allowances += $amount;
                else $deductions += $amount;
            }
        }

        if ($overtimePay > 0) {
            $allowances += $overtimePay;
            $details[] = [
                'name'    => 'Overtime Pay',
                'code'    => 'OT',
                'type'    => 'allowance',
                'amount'  => $overtimePay,
                'taxable' => true,
            ];
        }

        // GPA/WMC — employer cost, recorded for billing reference only (NOT added to employee gross)
        if ($gpaMwcAmount > 0) {
            $details[] = [
                'name'    => 'GPA/WMC (Employer Cost)',
                'code'    => 'GPA_WMC',
                'type'    => 'employer_cost',
                'amount'  => $gpaMwcAmount,
                'taxable' => false,
            ];
        }

        // ── Unpaid leave deduction ──────────────────────────────────
        //
        // Only leave that was actually granted is deducted. This read
        // 'rejected' — the one status that means the days were never taken —
        // so it docked pay for leave that had been refused and let genuine
        // unpaid leave through untouched. Nobody noticed because no unpaid
        // leave has been requested yet; the rule was simply never exercised.
        //
        // The days are counted over the overlap with this payroll period
        // rather than the whole request. The window matched a request on
        // either its start or its end date and then summed days_count, so an
        // absence straddling month-end was charged in full against both
        // months — 9 days of leave taking 18 days of pay. That only mattered
        // once the status above was right, which is why both are fixed here.
        $unpaidLeaveDays = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereHas('leaveType', fn($q) => $q->where('is_paid', false))
            ->where('from_date', '<=', $end->toDateString())
            ->where('to_date', '>=', $start->toDateString())
            ->get()
            ->sum(fn($leave) => $this->leaveDaysInPeriod($leave, $start, $end));

        $leaveDeductionRate = match($salaryType) {
            'daily'  => $rateValue,
            'hourly' => $rateValue * 8,
            default  => $dailyRate > 0 ? $dailyRate : ($totalCalendarDays > 0 ? $rateValue / $totalCalendarDays : 0),
        };

        $leaveDeduction = round($unpaidLeaveDays > 0 ? $leaveDeductionRate * $unpaidLeaveDays : 0, 0);

        if ($leaveDeduction > 0) {
            $deductions += $leaveDeduction;
            $details[] = [
                'name'    => 'Unpaid Leave Deduction (' . $unpaidLeaveDays . ' days)',
                'code'    => 'LEAVE_DED',
                'type'    => 'deduction',
                'amount'  => $leaveDeduction,
                'taxable' => false,
            ];
        }

        $gross   = $basic + $allowances;
        $taxable = $gross;

        $chargePaye = ($employee->charge_paye ?? true);
        $paye = 0;
        if ($chargePaye) {
            $paye = $this->calculatePAYE($taxable);
            $details[]  = ['name' => 'PAYE (Income Tax)', 'code' => 'PAYE', 'type' => 'deduction', 'amount' => $paye, 'taxable' => false];
            $deductions += $paye;
        }

        // Withholding tax: a flat percentage of the gross payment, not a
        // graduated scale. Separate from PAYE because they answer to different
        // arrangements — a consultant is withheld, an employee is taxed — and
        // an employee set up for both would legitimately carry both lines.
        //
        // The rate is read from the employee rather than assumed at 6%, so a
        // reprinted payslip shows the rate that was actually applied.
        if ($employee->charge_wht) {
            $whtRate = (float) ($employee->wht_percentage ?? 6);
            $wht = round($gross * $whtRate / 100, 0);

            if ($wht > 0) {
                $details[] = [
                    'name'    => 'Withholding Tax (' . rtrim(rtrim(number_format($whtRate, 2), '0'), '.') . '%)',
                    'code'    => 'WHT',
                    'type'    => 'deduction',
                    'amount'  => $wht,
                    'taxable' => false,
                ];
                $deductions += $wht;
            }
        }

        $net = max(0, round($gross - $deductions, 0));

        // Employer NSSF (10%): if no NSSF_CO component is attached to this
        // salary, derive it from the employee's 5% share — 10% is twice 5%.
        if ($employerNssf == 0 && $employeeNssf > 0) {
            $employerNssf = round($employeeNssf * 2, 0);
            $details[] = [
                'name'    => 'NSSF (Employer 10%)',
                'code'    => 'NSSF_CO',
                'type'    => 'employer_cost',
                'amount'  => $employerNssf,
                'taxable' => false,
            ];
        }

        // Public holiday pay is already inside basic (it is paid in day-units),
        // so this is recorded as a note rather than a component — it must not be
        // added to gross a second time.
        if ($holidayPay['extra_days'] > 0 || $holidayPay['worked']) {
            $details[] = [
                'name'    => 'Public holiday pay',
                'code'    => 'HOLIDAY_INFO',
                'type'    => 'note',
                'amount'  => 0,
                'taxable' => false,
                'meta'    => [
                    'extra_days'        => $holidayPay['extra_days'],
                    'worked'            => $holidayPay['worked'],
                    'unapproved_worked' => $holidayPay['unapproved_worked'],
                ],
            ];
        }

        // PAYE prints before NSSF on the payslip — see Payslip::orderComponents().
        $details = Payslip::orderComponents($details);

        return Payslip::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'basic_salary'        => $basic,
                'total_allowances'    => round($allowances, 0),
                'gross_salary'        => round($gross, 0),
                'total_deductions'    => round($deductions, 0),
                'tax_amount'          => round($paye, 0),
                'employee_nssf'       => round($employeeNssf, 0),
                'employer_nssf'       => round($employerNssf, 0),
                'net_salary'          => $net,
                'worked_days'         => $workedDays,
                'absent_days'         => $absentDays,
                'component_details'   => $details,
                'overtime_pay'        => $overtimePay,
                'leave_deduction'     => $leaveDeduction,
                'prorate_factor'      => $prorateFactor,
                'overtime_hours_paid' => (int) round($totalOvertimeHours),
            ]
        );
    }

    private function calculatePAYE(float $monthlyTaxable): float
    {
        $tax = 0;
        foreach ($this->taxBrackets as [$min, $max, $rate, $base]) {
            if ($monthlyTaxable > $min) {
                $over = min($monthlyTaxable, $max) - $min;
                $tax  = $base + ($over * $rate);
            }
        }
        return max(0, round($tax, 0));
    }

    /**
     * Gross-up: find G such that G - PAYE(G) - 0.05*G = net
     * i.e., employee takes home exactly 'net' after PAYE + NSSF5% deductions.
     * Uses iterative solving within the Uganda tax brackets.
     */
    public function grossUpAmount(float $net): float
    {
        // Try each bracket to find which one the gross falls in
        $nssf = 0.05; // 5% NSSF employee
        // Bracket boundaries for gross
        $boundaries = [0, 235000, 335000, 410000, 10000000, PHP_INT_MAX];
        $rates       = [0.0, 0.10, 0.20, 0.30, 0.40];
        $bases       = [0,   0,    10000, 25000, 2902000];

        foreach ($rates as $i => $payeRate) {
            // In bracket i: PAYE = bases[i] + (G - boundaries[i]) * payeRate
            // Net = G - PAYE - NSSF5%
            // Net = G - bases[i] - (G - boundaries[i]) * payeRate - G * nssf
            // Net = G(1 - payeRate - nssf) - bases[i] + boundaries[i] * payeRate
            $denom = 1 - $payeRate - $nssf;
            if ($denom <= 0) continue;
            $gross = ($net + $bases[$i] - $boundaries[$i] * $payeRate) / $denom;
            if ($gross >= $boundaries[$i] && $gross <= $boundaries[$i + 1]) {
                return round($gross, 0);
            }
        }
        // Fallback: simple gross-up at 30% bracket
        return round(($net + 25000 - 410001 * 0.30) / (1 - 0.30 - 0.05), 0);
    }

    /**
     * Public holiday pay for one casual employee over the payroll period.
     *
     * Returns:
     *   extra_days        day-units to add on top of days worked
     *   worked            holiday names this employee worked
     *   unapproved_worked holiday names worked with no approval — paid at the
     *                     normal rate, not double, and surfaced on the payslip
     *                     so HR can see the decision was missed
     *
     * @return array{extra_days:int, worked:array<string>, unapproved_worked:array<string>}
     */
    private function holidayPayAdjustment(Employee $employee, PayrollRun $run, Carbon $start, Carbon $end): array
    {
        // Only holidays that fall while this person was actually employed.
        $from = $employee->hire_date && $employee->hire_date->gt($start)
            ? $employee->hire_date->copy() : $start;
        $to   = $employee->end_date && $employee->end_date->lt($end)
            ? $employee->end_date->copy() : $end;

        if ($from->gt($to)) {
            return ['extra_days' => 0, 'worked' => [], 'unapproved_worked' => []];
        }

        $holidays = PublicHoliday::where('country', 'UG')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')->get();

        if ($holidays->isEmpty()) {
            return ['extra_days' => 0, 'worked' => [], 'unapproved_worked' => []];
        }

        $workedMap = HolidayWork::workedMap($holidays->pluck('id')->all());
        $clientId  = $run->client_id;

        $extra = 0; $worked = []; $unapproved = [];

        foreach ($holidays as $holiday) {
            $didWork  = in_array($employee->id, $workedMap[$holiday->id] ?? [], true);
            $approved = HolidayPayApproval::isPayableFor($holiday->id, $clientId);

            if ($approved) {
                // +1 whether or not they worked: it either pays the holiday they
                // sat out, or doubles the day they put in.
                $extra++;
            } elseif ($didWork) {
                // Worked without a decision — paid once (already in days worked),
                // never doubled, and recorded so HR can see it happened.
                $unapproved[] = $holiday->name;
            }

            if ($didWork) $worked[] = $holiday->name;
        }

        return ['extra_days' => $extra, 'worked' => $worked, 'unapproved_worked' => $unapproved];
    }

    /**
     * How many of a leave request's days fall inside this payroll period.
     *
     * days_count is the authoritative length of the absence — it may already
     * exclude weekends or holidays — so a request lying wholly inside the
     * period is taken at face value. One that straddles a boundary is
     * apportioned by the share of its calendar days that fall inside, which
     * keeps the two months' deductions adding up to the one absence.
     */
    private function leaveDaysInPeriod(LeaveRequest $leave, Carbon $start, Carbon $end): float
    {
        $days = (float) $leave->days_count;
        if ($days <= 0) return 0.0;

        $from = Carbon::parse($leave->from_date)->startOfDay();
        $to   = Carbon::parse($leave->to_date)->startOfDay();
        if ($from->gt($to)) return 0.0;

        $periodStart = $start->copy()->startOfDay();
        $periodEnd   = $end->copy()->startOfDay();

        if ($from->gte($periodStart) && $to->lte($periodEnd)) return $days;

        $overlapFrom = $from->lt($periodStart) ? $periodStart : $from;
        $overlapTo   = $to->gt($periodEnd)     ? $periodEnd   : $to;
        if ($overlapFrom->gt($overlapTo)) return 0.0;

        $totalSpan   = $from->diffInDays($to) + 1;
        $overlapSpan = $overlapFrom->diffInDays($overlapTo) + 1;

        return $totalSpan > 0 ? round($days * ($overlapSpan / $totalSpan), 2) : 0.0;
    }
    private function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $count  = 0;
        $period = CarbonPeriod::create($from->toDateString(), $to->toDateString());
        foreach ($period as $day) {
            if (!$day->isWeekend()) $count++;
        }
        return $count;
    }
}
