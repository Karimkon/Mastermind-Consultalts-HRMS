<?php
namespace App\Services\Payroll;

use App\Models\{Employee, PayrollRun, Payslip, EmployeeSalary, SalaryComponent, AttendanceLog, LeaveRequest, PayrollManualDays, PublicHoliday};
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PayrollService
{
    private array $taxBrackets = [
        [0,        235000,     0.00, 0],
        [235001,   335000,     0.10, 0],
        [335001,   410000,     0.20, 10000],
        [410001,   10000000,   0.30, 25000],
        [10000001, PHP_INT_MAX, 0.40, 2902000],
    ];

    public function processRun(PayrollRun $run): int
    {
        $today = now()->toDateString();
        $query = Employee::whereIn('status', ['active', 'on_leave'])
            ->where('is_blacklisted', false)
            ->where(function ($q) use ($today) {
                $q->where('on_hold', false)
                  ->orWhere(function ($q2) use ($today) {
                      $q2->where('on_hold', true)->whereNotNull('hold_end_date')->where('hold_end_date', '<', $today);
                  });
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('contract_end_date')->orWhere('contract_end_date', '>=', $today);
            });

        if ($run->client_id) {
            $query->whereHas('clients', fn($q) => $q->where('clients.id', $run->client_id));
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

        if ($manualDays) {
            // Manual upload: days_worked already includes public holidays (HR knows the calendar)
            $workedDays = (int) $manualDays->days_worked;
            $absentDays = max(0, $totalCalendarDays - $workedDays);
            $holidayBonusDays = 0;
        } else {
            // Attendance-log based: count present days, then add public holidays (paid by default)
            $presentDays = AttendanceLog::where('employee_id', $employee->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'present')->count();

            $absentDays = AttendanceLog::where('employee_id', $employee->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'absent')->count();

            // Public holidays are paid regardless — add days for holidays not already in attendance
            $holidayBonusDays = PublicHoliday::countInRange(
                $start->toDateString(), $end->toDateString()
            );

            $workedDays = $presentDays + $holidayBonusDays;
        }

        // ── Overtime ────────────────────────────────────────────────
        $totalOvertimeHours = (float) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('overtime_hours');

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
        $allowances = 0;
        $deductions = 0;
        $details    = [];

        if ($salary && $salary->components) {
            foreach ($salary->components as $comp) {
                $component = SalaryComponent::find($comp['component_id'] ?? null);
                if (!$component || !$component->is_active) continue;
                if ($component->code === 'NSSF_CO') continue;

                $amount = $component->is_fixed
                    ? (float) ($comp['amount'] ?? $component->amount)
                    : round($basic * ((float)($comp['percentage'] ?? $component->percentage)) / 100, 0);

                $details[] = [
                    'name'    => $component->name,
                    'code'    => $component->code,
                    'type'    => $component->type,
                    'amount'  => $amount,
                    'taxable' => (bool)$component->is_taxable,
                ];

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
        $unpaidLeaveDays = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['rejected'])
            ->whereHas('leaveType', fn($q) => $q->where('is_paid', false))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('from_date', [$start->toDateString(), $end->toDateString()])
                  ->orWhereBetween('to_date', [$start->toDateString(), $end->toDateString()]);
            })->sum('days_count');

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

        $net = max(0, round($gross - $deductions, 0));

        return Payslip::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'basic_salary'        => $basic,
                'total_allowances'    => round($allowances, 0),
                'gross_salary'        => round($gross, 0),
                'total_deductions'    => round($deductions, 0),
                'tax_amount'          => round($paye, 0),
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
