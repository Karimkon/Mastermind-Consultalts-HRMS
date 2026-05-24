<?php
namespace App\Exports;

use App\Models\{PayrollRun, Setting};
use App\Support\BankCodes;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class KcbEftExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    private string $debitAccount;
    private string $sortCode;
    private string $reference;

    public function __construct(private PayrollRun $run)
    {
        $this->debitAccount = Setting::get('kcb_account_number', '');
        $this->sortCode     = Setting::get('kcb_sort_code', '252947');
        $this->reference    = 'SALARY ' . date('M Y', mktime(0, 0, 0, $run->month, 1, $run->year));
    }

    public function array(): array
    {
        $rows = [];

        // Row 1: Title
        $rows[] = [null, 'Customer - Multi Debit payments', null, null, null, null, null, null, null];

        // Row 2: Headers (exact KCB column names)
        $rows[] = [
            'Debit /From Account',
            'Your Branch / Originator SORT Code',
            'Beneficiary Name',
            'Credit/To Account',
            'Beneficiary Bank',
            'BIC/SORT Code',
            'Amount',
            'My reference',
            'Beneficiary Ref',
        ];

        // Data rows — bank-paying employees only
        $total = 0;
        $slips = $this->run->payslips()
            ->with('employee')
            ->get()
            ->filter(fn($s) => in_array($s->employee?->payment_mode, ['bank', null, '']) && !empty($s->employee?->bank_account));

        foreach ($slips as $slip) {
            $emp     = $slip->employee;
            $net     = (float) $slip->net_salary;
            $total  += $net;
            $rows[] = [
                $this->debitAccount,
                $this->sortCode,
                strtoupper($emp->full_name),
                $emp->bank_account ?? '',
                $emp->bank_name ?? '',
                BankCodes::sortCode($emp->bank_name ?? ''),
                $net,
                $this->reference,
                $emp->emp_number,
            ];
        }

        // Total row (Amount column only)
        $rows[] = [null, null, null, null, null, null, $total, null, null];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Title row
        $sheet->mergeCells('B1:I1');
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 12],
        ]);

        // Header row
        $sheet->getStyle('A2:I2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Total row
        $sheet->getStyle("G{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
        ]);

        // Amount column — number format
        $sheet->getStyle("G3:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Borders on data
        $sheet->getStyle("A2:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22, 'B' => 30, 'C' => 28,
            'D' => 22, 'E' => 28, 'F' => 16,
            'G' => 16, 'H' => 22, 'I' => 16,
        ];
    }

    public function title(): string { return 'EFT Bank Transfer'; }
}
