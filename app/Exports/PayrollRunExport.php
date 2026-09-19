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
        return $this->run->payslips()->with('employee.department', 'employee.designation')->get()
            ->filter(fn ($slip) => $slip->employee !== null)
            ->map(function ($slip) {
                $emp = $slip->employee;
                return [
                    $emp->emp_number ?? '',
                    $emp->payroll_number ?? '',
                    $emp->full_name,
                    $emp->department?->name ?? '',
                    $emp->designation?->title ?? '',
                    $emp->paymentChannelLabel(),
                    $emp->bank_name ?? '',
                    $emp->bank_account ?? '',
                    $emp->payoutNumber(),
                    number_format($slip->basic_salary ?? 0, 2, '.', ''),
                    number_format($slip->gross_salary ?? 0, 2, '.', ''),
                    number_format($slip->tax_amount ?? 0, 2, '.', ''),
                    number_format($slip->employeeNssf(), 2, '.', ''),
                    number_format($slip->total_deductions ?? 0, 2, '.', ''),
                    number_format($slip->net_salary ?? 0, 2, '.', ''),
                    number_format($slip->employerNssf(), 2, '.', ''),
                    number_format($slip->totalNssf(), 2, '.', ''),
                    $emp->payoutIssue() ?? 'Ready',
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Emp No', 'Payroll No', 'Full Name', 'Department', 'Designation',
            'Payment Mode', 'Bank Name', 'Account No', 'Mobile Money No',
            'Basic Salary', 'Gross Salary', 'Tax (PAYE)', 'NSSF (Emp 5%)',
            'Total Deductions', 'Net Pay (UGX)',
            'NSSF (Employer 10%)', 'Total NSSF Remitted (15%)', 'Payment Status',
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
