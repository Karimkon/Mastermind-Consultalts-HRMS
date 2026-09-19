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
        return $this->run->payslips()->with('employee')->get()
            ->filter(fn ($slip) => $slip->employee !== null)
            ->map(fn ($slip) => [
                $slip->employee->emp_number ?? '',
                $slip->employee->full_name,
                $slip->employee->nssf_number ?? '',
                $slip->employee->paymentChannelLabel(),
                $slip->employee->bank_name ?? '',
                $slip->employee->bank_account ?? '',
                $slip->employee->bank_branch ?? '',
                $slip->employee->payoutNumber(),
                number_format($slip->gross_salary ?? 0, 2, '.', ''),
                number_format($slip->tax_amount ?? 0, 2, '.', ''),
                number_format($slip->employeeNssf(), 2, '.', ''),
                number_format($slip->employerNssf(), 2, '.', ''),
                number_format($slip->net_salary, 2, '.', ''),
                // Withheld slips stay on this schedule on purpose — Finance needs to
                // see who was held back — but they are excluded from the KCB files.
                $slip->payment_status === 'withheld'
                    ? 'WITHHELD' . ($slip->withheld_reason ? ' — ' . $slip->withheld_reason : '')
                    : ($slip->employee->payoutIssue() ?? 'Ready'),
            ])
            ->values();
    }

    public function headings(): array
    {
        return ['Employee No', 'Full Name', 'NSSF No', 'Payment Mode', 'Bank Name', 'Account Number',
                'Branch Code', 'Mobile Money No', 'Gross (UGX)', 'PAYE (UGX)',
                'NSSF Employee 5% (UGX)', 'NSSF Employer 10% (UGX)', 'Net Pay (UGX)', 'Status'];
    }

    public function title(): string { return 'Bank Payments'; }
}
