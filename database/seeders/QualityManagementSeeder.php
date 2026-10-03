<?php

namespace Database\Seeders;

use App\Models\QualityCheck;
use App\Models\QualityStandard;
use Illuminate\Database\Seeder;

/**
 * Registers the starting set of quality standards and the automated checks
 * that measure them. Idempotent: keyed on code / engine_key, so re-running
 * updates in place and never duplicates.
 */
class QualityManagementSeeder extends Seeder
{
    public function run(): void
    {
        $standards = [
            ['code' => 'QS-EMP-01', 'title' => 'Statutory identifiers complete', 'hr_function' => 'employee_central', 'category' => 'compliance', 'severity' => 'high', 'weight' => 20, 'description' => 'Every active employee carries the statutory numbers Uganda requires (NSSF, TIN).'],
            ['code' => 'QS-EMP-02', 'title' => 'Core employment record complete', 'hr_function' => 'employee_central', 'category' => 'data_quality', 'severity' => 'medium', 'weight' => 15, 'description' => 'Department, designation and hire date are recorded for every active employee.'],
            ['code' => 'QS-EMP-03', 'title' => 'Payment details valid', 'hr_function' => 'employee_central', 'category' => 'process', 'severity' => 'high', 'weight' => 15, 'description' => 'Employees paid by bank have complete bank details.'],
            ['code' => 'QS-EMP-04', 'title' => 'Contactable records', 'hr_function' => 'employee_central', 'category' => 'data_quality', 'severity' => 'medium', 'weight' => 10, 'description' => 'Each employee can be reached and has an emergency contact.'],
            ['code' => 'QS-EMP-05', 'title' => 'Probation decisions on time', 'hr_function' => 'employee_central', 'category' => 'process', 'severity' => 'high', 'weight' => 10, 'description' => 'Probation is confirmed or ended by its due date.'],
            ['code' => 'QS-PAY-01', 'title' => 'Payslip figures are sound', 'hr_function' => 'payroll', 'category' => 'accuracy', 'severity' => 'critical', 'weight' => 25, 'description' => 'No payslip has impossible figures (negative net, net above gross).'],
            ['code' => 'QS-PAY-02', 'title' => 'Only active staff are paid', 'hr_function' => 'payroll', 'category' => 'compliance', 'severity' => 'high', 'weight' => 20, 'description' => 'Processed payslips belong to currently active employees.'],
            ['code' => 'QS-CMP-01', 'title' => 'Statutory identity & right to work', 'hr_function' => 'compliance', 'category' => 'compliance', 'severity' => 'high', 'weight' => 20, 'description' => 'Every active employee has a National ID; expatriates have a passport on file.'],
            ['code' => 'QS-CMP-02', 'title' => 'Contracts kept current', 'hr_function' => 'compliance', 'category' => 'compliance', 'severity' => 'high', 'weight' => 15, 'description' => 'Fixed-term contracts are renewed or closed before they lapse.'],
        ];

        $byCode = [];
        foreach ($standards as $s) {
            $std = QualityStandard::updateOrCreate(['code' => $s['code']], $s + ['is_active' => true]);
            $byCode[$s['code']] = $std->id;
        }

        $checks = [
            ['engine_key' => 'employee.missing_nssf', 'name' => 'Missing NSSF number', 'hr_function' => 'employee_central', 'severity' => 'high', 'weight' => 10, 'standard' => 'QS-EMP-01', 'description' => 'Active employees without an NSSF number on file.'],
            ['engine_key' => 'employee.missing_tin', 'name' => 'Missing TIN', 'hr_function' => 'employee_central', 'severity' => 'high', 'weight' => 10, 'standard' => 'QS-EMP-01', 'description' => 'Active employees without a tax identification number.'],
            ['engine_key' => 'employee.missing_hire_date', 'name' => 'Missing hire date', 'hr_function' => 'employee_central', 'severity' => 'high', 'weight' => 8, 'standard' => 'QS-EMP-02', 'description' => 'Active employees with no recorded hire date.'],
            ['engine_key' => 'employee.missing_department', 'name' => 'Missing department', 'hr_function' => 'employee_central', 'severity' => 'medium', 'weight' => 5, 'standard' => 'QS-EMP-02', 'description' => 'Active employees not assigned to a department.'],
            ['engine_key' => 'employee.missing_designation', 'name' => 'Missing designation', 'hr_function' => 'employee_central', 'severity' => 'low', 'weight' => 3, 'standard' => 'QS-EMP-02', 'auto_raise_nc' => false, 'description' => 'Active employees without a job title.'],
            ['engine_key' => 'employee.missing_bank', 'name' => 'Incomplete bank details', 'hr_function' => 'employee_central', 'severity' => 'high', 'weight' => 10, 'standard' => 'QS-EMP-03', 'description' => 'Employees paid by bank with missing account or bank name.'],
            ['engine_key' => 'employee.missing_contact', 'name' => 'No contact details', 'hr_function' => 'employee_central', 'severity' => 'medium', 'weight' => 5, 'standard' => 'QS-EMP-04', 'description' => 'Active employees with neither phone nor personal email.'],
            ['engine_key' => 'employee.missing_emergency_contact', 'name' => 'No emergency contact', 'hr_function' => 'employee_central', 'severity' => 'low', 'weight' => 3, 'standard' => 'QS-EMP-04', 'auto_raise_nc' => false, 'description' => 'Active employees with no emergency or next-of-kin phone.'],
            ['engine_key' => 'employee.probation_overdue', 'name' => 'Overdue probation decision', 'hr_function' => 'employee_central', 'severity' => 'high', 'weight' => 8, 'standard' => 'QS-EMP-05', 'description' => 'Probation period has ended but the outcome was never confirmed.'],
            ['engine_key' => 'payroll.negative_net', 'name' => 'Negative net pay', 'hr_function' => 'payroll', 'severity' => 'critical', 'weight' => 12, 'standard' => 'QS-PAY-01', 'description' => 'A payslip resolves to a net below zero.'],
            ['engine_key' => 'payroll.net_exceeds_gross', 'name' => 'Net pay exceeds gross', 'hr_function' => 'payroll', 'severity' => 'high', 'weight' => 10, 'standard' => 'QS-PAY-01', 'description' => 'Net pay is larger than gross - a calculation fault.'],
            ['engine_key' => 'payroll.zero_gross', 'name' => 'Zero gross pay', 'hr_function' => 'payroll', 'severity' => 'medium', 'weight' => 5, 'standard' => 'QS-PAY-01', 'auto_raise_nc' => false, 'description' => 'A processed payslip has zero gross pay.'],
            ['engine_key' => 'payroll.inactive_employee_paid', 'name' => 'Non-active employee paid', 'hr_function' => 'payroll', 'severity' => 'high', 'weight' => 12, 'standard' => 'QS-PAY-02', 'description' => 'A processed payslip belongs to an employee who is not active.'],
            ['engine_key' => 'compliance.missing_national_id', 'name' => 'Missing National ID', 'hr_function' => 'compliance', 'severity' => 'high', 'weight' => 10, 'standard' => 'QS-CMP-01', 'description' => 'Active employees without a National ID (NIN) recorded.'],
            ['engine_key' => 'compliance.expatriate_no_passport', 'name' => 'Expatriate without passport', 'hr_function' => 'compliance', 'severity' => 'high', 'weight' => 8, 'standard' => 'QS-CMP-01', 'description' => 'Employees marked expatriate with no passport number.'],
            ['engine_key' => 'compliance.contract_expired', 'name' => 'Expired contract still active', 'hr_function' => 'compliance', 'severity' => 'high', 'weight' => 10, 'standard' => 'QS-CMP-02', 'description' => 'A fixed-term contract has lapsed but the employee is still active.'],
            ['engine_key' => 'compliance.contract_expiring', 'name' => 'Contract expiring within 30 days', 'hr_function' => 'compliance', 'severity' => 'medium', 'weight' => 5, 'standard' => 'QS-CMP-02', 'auto_raise_nc' => false, 'description' => 'A contract will lapse within the next 30 days.'],
        ];

        foreach ($checks as $c) {
            $stdCode = $c['standard'] ?? null;
            unset($c['standard']);
            $c['quality_standard_id'] = $stdCode ? ($byCode[$stdCode] ?? null) : null;
            $c['is_automated'] = true;
            $c['is_active'] = true;
            $c['auto_raise_nc'] = $c['auto_raise_nc'] ?? true;

            QualityCheck::updateOrCreate(['engine_key' => $c['engine_key']], $c);
        }
    }
}
