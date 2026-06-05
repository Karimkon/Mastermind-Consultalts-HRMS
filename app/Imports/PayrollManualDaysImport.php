<?php
namespace App\Imports;

use App\Models\{Employee, PayrollManualDays};
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

/**
 * Import manual days worked per employee for a payroll run.
 * Expected columns: emp_number (or full_name), days_worked, notes (optional)
 */
class PayrollManualDaysImport implements ToModel, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    public function __construct(private int $payrollRunId) {}

    public function model(array $row)
    {
        $days = (int) ($row['days_worked'] ?? 0);
        if ($days < 0) $days = 0;

        // Look up employee by emp_number first, then by full name
        $employee = null;
        if (!empty($row['emp_number'])) {
            $employee = Employee::where('emp_number', trim($row['emp_number']))->first();
        }
        if (!$employee && !empty($row['full_name'])) {
            $name  = trim($row['full_name']);
            $parts = explode(' ', $name, 2);
            $employee = Employee::where('last_name', $parts[0])
                ->where(function ($q) use ($parts) {
                    if (isset($parts[1])) $q->where('first_name', 'like', $parts[1] . '%');
                })->first();
        }
        if (!$employee) return null;

        return new PayrollManualDays([
            'payroll_run_id' => $this->payrollRunId,
            'employee_id'    => $employee->id,
            'days_worked'    => $days,
            'notes'          => $row['notes'] ?? null,
        ]);
    }
}
