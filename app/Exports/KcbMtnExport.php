<?php
namespace App\Exports;

use App\Models\PayrollRun;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class KcbMtnExport implements FromArray, WithEvents
{
    public function __construct(private PayrollRun $run) {}

    public function array(): array
    {
        $kcbAccount = Setting::get("kcb_account_number", "");

        $rows = [
            ["Customer  payments", null, null, null, null, null, null],
            ["Debit /From Account", "Your Branch / Originator SORT Code", "Beneficiary Name",
             "MNO", "MNO Code", "Mobile Number", "Amount"],
        ];

        $payslips = $this->run->payableSlips("mtn")
            ->filter(fn($s) => $s->employee->payoutNumber() !== "");

        foreach ($payslips as $slip) {
            $emp = $slip->employee;
            $rows[] = [
                $kcbAccount,
                252947,
                $emp->full_name,
                "MTN",
                989999,
                $emp->payoutNumber(),
                (int) $slip->net_salary,
            ];
        }
        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = $sheet->getHighestRow();
                for ($i = 3; $i <= $last; $i++) {
                    foreach (["A", "F"] as $col) {
                        $cell = $sheet->getCell("{$col}{$i}");
                        $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
                    }
                }
                $sheet->getColumnDimension("A")->setWidth(22);
                $sheet->getColumnDimension("C")->setWidth(28);
                $sheet->getColumnDimension("F")->setWidth(18);
            },
        ];
    }
}
