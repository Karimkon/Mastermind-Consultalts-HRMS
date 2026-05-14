<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AmEmployeesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private Collection $employees) {}

    public function collection(): Collection
    {
        return $this->employees;
    }

    public function headings(): array
    {
        return [
            'Emp Number', 'First Name', 'Last Name', 'Email', 'Phone',
            'Department', 'Designation', 'Status', 'Hire Date',
            'Basic Salary (UGX)', 'Address', 'City',
            'Emergency Contact', 'Emergency Phone',
        ];
    }

    public function map($emp): array
    {
        return [
            $emp->emp_number,
            $emp->first_name,
            $emp->last_name,
            $emp->user?->email ?? '',
            $emp->phone ?? '',
            $emp->department?->name ?? '',
            $emp->designation?->name ?? '',
            ucfirst($emp->status),
            $emp->hire_date ? \Carbon\Carbon::parse($emp->hire_date)->format('d M Y') : '',
            $emp->salary?->basic_salary ?? '',
            $emp->address ?? '',
            $emp->city ?? '',
            $emp->emergency_contact_name ?? '',
            $emp->emergency_contact_phone ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1e3a8a']],
            ],
        ];
    }
}
