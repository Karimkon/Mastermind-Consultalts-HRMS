<?php
namespace App\Imports;

use App\Models\{Employee, User, Department, Designation};
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Illuminate\Support\Facades\Hash;

/**
 * Partial-update import: only columns present in the Excel sheet are updated.
 * Photos, documents, and columns NOT in the sheet are left untouched.
 * Lookup key: emp_number (must exist in sheet to update an existing employee).
 */
class EmployeesImport implements ToModel, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    // All updatable string/date columns (not booleans, not relations)
    private array $textColumns = [
        'payroll_number','title','first_name','middle_name','last_name',
        'employment_type','hire_date','end_date','status','salary_grade',
        'work_location','sub_department','employee_category','class_name','position_name','organization_unit',
        'phone','personal_email','date_of_birth','gender','marital_status',
        'children_count','dependents_count','nationality','religion','mother_tongue',
        'national_id','passport_number','address','city','country',
        'emergency_contact_name','emergency_contact_phone',
        'next_of_kin_name','next_of_kin_relation','next_of_kin_phone','next_of_kin_email',
        'nssf_number','tin_number','ifms_supplier_no','pension_no','tax_number',
        'bank_name','bank_account','bank_branch','payment_mode',
        'ot_calc_hours','absenteeism_calc_hours','ot1_calc_hours','ot2_calc_hours','min_daily_working_hours',
        'bio',
    ];

    public function model(array $row)
    {
        if (empty($row['emp_number']) && empty($row['email'])) return null;

        $dept  = !empty($row['department'])   ? Department::where('name',  $row['department'])->first()  : null;
        $desig = !empty($row['designation'])  ? Designation::where('title', $row['designation'])->first() : null;

        // Try to find existing employee
        $employee = !empty($row['emp_number'])
            ? Employee::where('emp_number', $row['emp_number'])->first()
            : null;

        if ($employee) {
            // PARTIAL UPDATE: only touch columns that appear in the sheet and have a value
            $updates = [];
            foreach ($this->textColumns as $col) {
                if (array_key_exists($col, $row) && !is_null($row[$col]) && $row[$col] !== '') {
                    $updates[$col] = $row[$col];
                }
            }
            if ($dept)  $updates['department_id']  = $dept->id;
            if ($desig) $updates['designation_id'] = $desig->id;

            if (!empty($updates)) {
                $employee->update($updates);
                // Update user name if name columns changed
                if (isset($updates['first_name']) || isset($updates['last_name'])) {
                    $employee->user?->update(['name' => trim(($employee->fresh()->first_name ?? '').' '.($employee->fresh()->last_name ?? ''))]);
                }
            }
            return null; // don't create duplicate
        }

        // NEW EMPLOYEE
        if (empty($row['email'])) return null;

        $user = User::firstOrCreate(
            ['email' => $row['email']],
            [
                'name'     => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'password' => Hash::make('Password@123'),
            ]
        );
        if (!$user->hasRole('employee')) $user->assignRole('employee');

        if (Employee::where('user_id', $user->id)->exists()) return null;

        $empNo = $row['emp_number'] ?? 'EMP' . str_pad(Employee::count() + 1, 4, '0', STR_PAD_LEFT);

        $data = [
            'user_id'         => $user->id,
            'emp_number'      => $empNo,
            'first_name'      => $row['first_name'] ?? '',
            'last_name'       => $row['last_name'] ?? '',
            'department_id'   => $dept?->id,
            'designation_id'  => $desig?->id,
            'hire_date'       => $row['hire_date'] ?? now()->format('Y-m-d'),
            'status'          => $row['status'] ?? 'active',
            'employment_type' => $row['employment_type'] ?? 'full_time',
        ];

        // Add any extra columns from the sheet
        foreach ($this->textColumns as $col) {
            if (!isset($data[$col]) && array_key_exists($col, $row) && !is_null($row[$col]) && $row[$col] !== '') {
                $data[$col] = $row[$col];
            }
        }

        return new Employee($data);
    }
}
