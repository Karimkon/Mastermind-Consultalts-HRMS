<?php
namespace App\Exports;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Monthly NSSF contribution schedule for a payroll run.
 *
 * This is the statutory return: one row per employee with the 5% employee share,
 * the 10% employer share and the 15% total remitted. The KCB payment files
 * deliberately carry only what the bank needs, so this is the file Finance uses
 * to reconcile and to file with NSSF.
 */
class NssfScheduleExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private PayrollRun $run) {}

    public function collection()
    {
        return $this->run->payslips()
            ->with('employee.department', 'employee.designation')
            ->get()
            ->filter(fn ($slip) => $slip->employee !== null)
            // Someone with no NSSF at all this period does not belong on the return.
            ->filter(fn ($slip) => $slip->totalNssf() > 0)
            ->sortBy(fn ($slip) => $slip->employee->full_name)
            ->map(function ($slip) {
                $emp = $slip->employee;
                return [
                    $emp->emp_number ?? '',
                    $emp->nssf_number ?? '',
                    $emp->full_name,
                    $emp->national_id ?? '',
                    $emp->date_of_birth ?? '',
                    $emp->gender ?? '',
                    $emp->department?->name ?? '',
                    $emp->designation?->title ?? '',
                    number_format($slip->gross_salary ?? 0, 2, '.', ''),
                    number_format($slip->employeeNssf(), 2, '.', ''),
                    number_format($slip->employerNssf(), 2, '.', ''),
                    number_format($slip->totalNssf(), 2, '.', ''),
                    $slip->payment_status === 'withheld' ? 'Withheld' : 'Included',
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Emp No', 'NSSF Number', 'Full Name', 'National ID', 'Date of Birth', 'Gender',
            'Department', 'Designation', 'Gross Pay (UGX)',
            'Employee 5% (UGX)', 'Employer 10% (UGX)', 'Total Remitted 15% (UGX)', 'Payroll Status',
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 16, 'C' => 28, 'D' => 16, 'E' => 14, 'F' => 10,
                'G' => 18, 'H' => 20, 'I' => 16, 'J' => 18, 'K' => 18, 'L' => 22, 'M' => 14];
    }

    public function title(): string
    {
        return 'NSSF ' . date('M', mktime(0, 0, 0, $this->run->month, 1)) . ' ' . $this->run->year;
    }

    public function styles(Worksheet $sheet): array
    {
        $last = $sheet->getHighestRow();

        // Totals row so the figure filed with NSSF is on the sheet, not recomputed by hand.
        if ($last > 1) {
            $sheet->setCellValue("H" . ($last + 1), 'TOTAL');
            foreach (['I', 'J', 'K', 'L'] as $col) {
                $sheet->setCellValue("{$col}" . ($last + 1), "=SUM({$col}2:{$col}{$last})");
            }
            $sheet->getStyle("H" . ($last + 1) . ":L" . ($last + 1))->getFont()->setBold(true);
        }

        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EDE9FE']]],
        ];
    }
}
