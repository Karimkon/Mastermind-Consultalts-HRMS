<?php
namespace App\Exports;

use App\Models\PayrollRun;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class KcbEftExport implements FromArray, WithEvents
{
    private const SORT_CODES = [
        "absa"            => "013847",
        "baroda"          => "020147",
        "stanbic"         => "040047",
        "guaranty"        => "270147",
        "finance trust"   => "370147",
        "centenary"       => "168547",
        "cairo"           => "180047",
        "diamond trust"   => "190047",
        "dtb"             => "190047",
        "dfcu"            => "053647",
        "tropical"        => "060147",
        "standard chart"  => "080147",
        "orient"          => "110147",
        "bank of africa"  => "130447",
        "citi"            => "220147",
        "equity"          => "300047",
        "abc"             => "310047",
        "exim"            => "320047",
        "bank of uganda"  => "990147",
        "bank of india"   => "340147",
        "ncba"            => "360147",
        "ecobank"         => "290147",
        "uba"             => "260147",
        "housing finance" => "230147",
        "kcb"             => "252947",
    ];

    public function __construct(private PayrollRun $run) {}

    public function array(): array
    {
        $kcbAccount = Setting::get("kcb_account_number", "");
        $ref = "SAL-" . $this->run->year . "-" . str_pad($this->run->month, 2, "0", STR_PAD_LEFT);

        $rows = [
            [null, "Customer - Multi Debit payments", null, null, null, null, null, null, null],
            ["Debit /From Account", "Your Branch / Originator SORT Code", "Beneficiary Name",
             "Credit/To Account", "Beneficiary Bank", "BIC/SORT Code", "Amount", "My reference", "Beneficiary Ref"],
        ];

        $payslips = $this->run->payslips()->with("employee")->get()
            ->filter(fn($s) => in_array($s->employee->payment_mode ?? "bank", ["bank", ""]));

        foreach ($payslips as $slip) {
            $emp = $slip->employee;
            $rows[] = [
                $kcbAccount,
                252947,
                $emp->full_name,
                $emp->bank_account ?? "",
                $emp->bank_name ?? "",
                $this->sortCode($emp->bank_name ?? ""),
                (int) $slip->net_salary,
                $ref,
                $emp->full_name,
            ];
        }
        return $rows;
    }

    private function sortCode(string $bank): string
    {
        $lower = strtolower($bank);
        foreach (self::SORT_CODES as $key => $code) {
            if (str_contains($lower, $key)) return $code;
        }
        return "";
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = $sheet->getHighestRow();
                for ($i = 3; $i <= $last; $i++) {
                    foreach (["A", "D"] as $col) {
                        $cell = $sheet->getCell("{$col}{$i}");
                        $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
                    }
                }
                $sheet->getColumnDimension("A")->setWidth(22);
                $sheet->getColumnDimension("C")->setWidth(28);
                $sheet->getColumnDimension("D")->setWidth(22);
                $sheet->getColumnDimension("E")->setWidth(22);
            },
        ];
    }
}
