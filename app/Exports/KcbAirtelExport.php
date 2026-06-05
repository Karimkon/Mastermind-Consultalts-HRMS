<?php
namespace App\Exports;

use App\Models\PayrollRun;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class KcbAirtelExport implements FromArray, WithEvents
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

        $payslips = $this->run->payslips()->with("employee")->get()
            ->filter(fn($s) => $s->employee->payment_mode === "airtel");

        foreach ($payslips as $slip) {
            $emp = $slip->employee;
            $rows[] = [
                $kcbAccount,
                252947,
                $emp->full_name,
                "AIRTEL",
                979999,
                $this->ugPhone($emp->mobile_money_number ?? $emp->phone ?? ""),
                (int) $slip->net_salary,
            ];
        }
        return $rows;
    }

    private function ugPhone(string $p): string
    {
        $p = preg_replace("/\D/", "", $p);
        if (str_starts_with($p, "0")) $p = "256" . substr($p, 1);
        if (!str_starts_with($p, "256")) $p = "256" . $p;
        return $p;
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
