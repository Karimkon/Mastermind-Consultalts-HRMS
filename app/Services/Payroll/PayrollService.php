<?php
namespace App\Services\Payroll;

use App\Models\{Employee, PayrollRun, Payslip, EmployeeSalary, SalaryComponent, AttendanceLog, LeaveRequest};
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PayrollService
{
    private array $taxBrackets = [
        [0,        235000,     0.00, 0],
        [235001,   335000,     0.10, 0],
        [335001,   410000,     0.20, 10000],
        [410001,   10000000,   0.30, 25000],
        [10000001, PHP_INT_MAX, 0.40, 2992000],
    ];

    public function processRun(PayrollRun $run): int
    {
        $employees = Employee::where('status', 'active')->orWhere('status', 'on_leave')->get();
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

        $basic = $salary ? (float) $salary->basic_salary : 0;

        $start = Carbon::create($run->year, $run->month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        // Total working days in the month (Mon–Fri)
        $totalWorkingDays = $this->countWorkingDays($start, $end);

        // ── Pro-rata factor ────────────────────────────────────────
        $prorateFactor = 1.0;
        $hireDate = $employee->hire_date;
        $endDate  = $employee->end_date;

        if ($hireDate && $hireDate->between($start, $end)) {
            // Mid-month join
            $workingDaysFromHire = $this->countWorkingDays($hireDate, $end);
            $prorateFactor = $totalWorkingDays > 0 ? round($workingDaysFromHire / $totalWorkingDays, 4) : 1;
        } elseif ($endDate && $endDate->between($start, $end)) {
            // Mid-month exit
            $workingDaysToExit = $this->countWorkingDays($start, $endDate);
            $prorateFactor = $totalWorkingDays > 0 ? round($workingDaysToExit / $totalWorkingDays, 4) : 1;
        }

        $basic = round($basic * $prorateFactor, 0);

        // ── Attendance ──────────────────────────────────────────────
        $workedDays = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'present')->count();
        $absentDays = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'absent')->count();

        // ── Overtime ────────────────────────────────────────────────
        $totalOvertimeHours = (float) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('overtime_hours');

        $dailyRate  = $totalWorkingDays > 0 ? ($salary ? (float)$salary->basic_salary / $totalWorkingDays : 0) : 0;
        $hourlyRate = $dailyRate / 8;
        $overtimePay = round($totalOvertimeHours * $hourlyRate * 1.5, 0);

        // ── Salary Components ───────────────────────────────────────
        $allowances = 0;
        $deductions = 0;
        $details    = [];

        if ($salary && $salary->components) {
            foreach ($salary->components as $comp) {
                $component = SalaryComponent::find($comp['component_id'] ?? null);
                if (!$component || !$component->is_active) continue;
                if ($component->code === 'NSSF_CO') continue; // employer cost

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

        // Add overtime pay as allowance
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

        // ── Leave deduction (unpaid unapproved absent days) ─────────
        $unpaidLeaveDays = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['rejected'])
            ->whereHas('leaveType', fn($q) => $q->where('is_paid', false))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('from_date', [$start->toDateString(), $end->toDateString()])
                  ->orWhereBetween('to_date', [$start->toDateString(), $end->toDateString()]);
            })->sum('days_count');

        $leaveDeduction = round(($unpaidLeaveDays > 0 && $totalWorkingDays > 0)
            ? ($salary ? (float)$salary->basic_salary / $totalWorkingDays * $unpaidLeaveDays : 0)
            : 0, 0);

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
        $taxable = max(0, $gross - $deductions);
        $paye    = $this->calculatePAYE($taxable);

        $details[]  = ['name' => 'PAYE (Income Tax)', 'code' => 'PAYE', 'type' => 'deduction', 'amount' => $paye, 'taxable' => false];
        $deductions += $paye;

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

    private function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $period = CarbonPeriod::create($from->toDateString(), $to->toDateString());
        foreach ($period as $day) {
            if (!$day->isWeekend()) $count++;
        }
        return $count;
    }
}
