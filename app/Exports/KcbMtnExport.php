<?php
namespace App\Exports;

use App\Models\{PayrollRun, Setting};
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class KcbMtnExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    private string $debitAccount;
    private string $sortCode;

    public function __construct(private PayrollRun $run)
    {
        $this->debitAccount = Setting::get('kcb_account_number', '');
        $this->sortCode     = Setting::get('kcb_sort_code', '252947');
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        // Strip leading 0 and prepend 256 (Uganda)
        if (str_starts_with($digits, '0'))   $digits = '256' . substr($digits, 1);
        if (str_starts_with($digits, '256')) return $digits;
        return '256' . $digits;
    }

    public function array(): array
    {
        $rows = [];

        // Row 1: Title
        $rows[] = ['Customer  payments', null, null, null, null, null, null];

        // Row 2: Headers (exact KCB column names)
        $rows[] = [
            'Debit /From Account',
            'Your Branch / Originator SORT Code',
            'Beneficiary Name',
            'MNO',
            'MNO Code',
            'Mobile Number',
            'Amount',
        ];

        // Data rows — MTN employees only
        $total = 0;
        $slips = $this->run->payslips()
            ->with('employee')
            ->get()
            ->filter(fn($s) => strtolower($s->employee?->payment_mode ?? '') === 'mtn' && !empty($s->employee?->phone));

        foreach ($slips as $slip) {
            $emp    = $slip->employee;
            $net    = (float) $slip->net_salary;
            $total += $net;
            $rows[] = [
                $this->debitAccount,
                $this->sortCode,
                strtoupper($emp->full_name),
                'MTN',
                989999,
                $this->formatPhone($emp->phone),
                $net,
            ];
        }

        // Total row
        $rows[] = [null, null, null, null, null, null, $total];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCC00']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FF000000'], 'size' => 12],
        ]);

        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCC00']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("G{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
        ]);

        $sheet->getStyle("G3:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A2:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 30, 'C' => 28, 'D' => 10, 'E' => 12, 'F' => 18, 'G' => 16];
    }

    public function title(): string { return 'MTN Mobile Money'; }
}
