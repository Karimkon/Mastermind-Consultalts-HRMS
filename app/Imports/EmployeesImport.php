<?php
namespace App\Imports;

use App\Models\{Employee, User, Department, Designation};
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;

class EmployeesImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $dept  = Department::where('name', $row['department'] ?? '')->first();
        $desig = Designation::where('title', $row['designation'] ?? '')->first();

        if (empty($row['email'])) return null;

        $user = User::firstOrCreate(
            ['email' => $row['email']],
            [
                'name'     => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'password' => Hash::make('Password@123'),
                'status'   => 'active',
            ]
        );
        $user->assignRole('employee');

        // Avoid duplicate employees for the same user
        if (Employee::where('user_id', $user->id)->exists()) {
            return null;
        }

        return new Employee([
            'user_id'          => $user->id,
            'emp_number'       => $row['emp_number'] ?? 'EMP' . str_pad(Employee::count() + 1, 4, '0', STR_PAD_LEFT),
            'first_name'       => $row['first_name'] ?? '',
            'last_name'        => $row['last_name'] ?? '',
            'department_id'    => $dept?->id,
            'designation_id'   => $desig?->id,
            'hire_date'        => $row['hire_date'] ?? now()->format('Y-m-d'),
            'status'           => 'active',
            'employment_type'  => $row['employment_type'] ?? 'full_time',
        ]);
    }
}
