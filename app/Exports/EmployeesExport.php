<?php
namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Employee::with(['department', 'designation', 'user'])->get();
    }

    public function headings(): array
    {
        return ['Emp No', 'Full Name', 'Email', 'Department', 'Designation', 'Employment Type', 'Hire Date', 'Status', 'Phone'];
    }

    public function map($emp): array
    {
        return [
            $emp->emp_number,
            $emp->first_name . ' ' . $emp->last_name,
            $emp->user?->email ?? '',
            $emp->department?->name ?? '',
            $emp->designation?->title ?? '',
            $emp->employment_type,
            $emp->hire_date ? \Carbon\Carbon::parse($emp->hire_date)->format('Y-m-d') : '',
            $emp->status,
            $emp->phone ?? '',
        ];
    }
}
