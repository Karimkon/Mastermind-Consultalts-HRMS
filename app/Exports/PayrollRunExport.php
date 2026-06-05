<?php
namespace App\Exports;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollRunExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private PayrollRun $run) {}

    public function collection()
    {
        return $this->run->payslips()->with('employee.department')->get()->map(function ($slip) {
            $nssf = 0;
            foreach ($slip->component_details ?? [] as $d) {
                if (($d['code'] ?? '') === 'NSSF_EMP') { $nssf = $d['amount'] ?? 0; break; }
            }
            return [
                $slip->employee->emp_number ?? '',
                $slip->employee->payroll_number ?? '',
                $slip->employee->full_name,
                $slip->employee->department?->name ?? '',
                $slip->employee->designation?->title ?? '',
                $slip->employee->bank_name ?? '',
                $slip->employee->bank_account ?? '',
                number_format($slip->basic_salary ?? 0, 2, '.', ''),
                number_format($slip->gross_salary ?? 0, 2, '.', ''),
                number_format($slip->total_deductions ?? 0, 2, '.', ''),
                number_format($slip->tax_amount ?? 0, 2, '.', ''),
                number_format($nssf, 2, '.', ''),
                number_format($slip->net_salary ?? 0, 2, '.', ''),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Emp No', 'Payroll No', 'Full Name', 'Department', 'Designation',
            'Bank Name', 'Account No',
            'Basic Salary', 'Gross Salary', 'Total Deductions', 'Tax (PAYE)', 'NSSF (Emp 5%)', 'Net Pay (UGX)',
        ];
    }

    public function title(): string
    {
        return date('F', mktime(0,0,0,$this->run->month,1)) . ' ' . $this->run->year . ' Payroll';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DBEAFE']]],
        ];
    }
}
