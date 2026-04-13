<?php
namespace App\Exports;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BankPaymentExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private PayrollRun $run) {}

    public function collection()
    {
        return $this->run->payslips()->with('employee')->get()->map(fn ($slip) => [
            $slip->employee->emp_number ?? '',
            $slip->employee->full_name,
            $slip->employee->bank_name ?? '',
            $slip->employee->bank_account ?? '',
            $slip->employee->bank_branch ?? '',
            number_format($slip->net_salary, 2, '.', ''),
        ]);
    }

    public function headings(): array
    {
        return ['Employee No', 'Full Name', 'Bank Name', 'Account Number', 'Branch Code', 'Net Pay (UGX)'];
    }

    public function title(): string { return 'Bank Payments'; }
}
