<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'basic_salary', 'total_allowances', 'gross_salary', 'total_deductions', 'tax_amount', 'employee_nssf', 'employer_nssf', 'net_salary', 'worked_days', 'absent_days', 'component_details', 'overtime_pay', 'leave_deduction', 'prorate_factor', 'overtime_hours_paid', 'payment_status', 'withheld_reason', 'withheld_stage'];
    protected $casts    = ['component_details' => 'array'];
    public function employee()   { return $this->belongsTo(Employee::class); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }

    /**
     * The statutory deductions, in the order a reviewer reads them.
     *
     * PAYE and NSSF have columns of their own. Withholding tax and local
     * service tax do not -- they are lines inside component_details -- so
     * they are read back by code. Keyed by code, valued by heading.
     */
    public const DEDUCTION_PARTS = [
        'PAYE'     => 'PAYE',
        'NSSF_EMP' => 'NSSF 5%',
        'WHT'      => 'WHT',
        'LST'      => 'LST',
    ];

    /** The two that are shown only on runs that actually carry them. */
    public const OPTIONAL_PARTS = ['WHT', 'LST'];

    /** What this payslip was deducted under one component code. */
    public function deductionFor(string $code): float
    {
        foreach ((array) $this->component_details as $line) {
            if (($line['code'] ?? null) === $code && ($line['type'] ?? null) === 'deduction') {
                return (float) ($line['amount'] ?? 0);
            }
        }

        return 0.0;
    }

    /** One part of the deduction, whichever way this system records it. */
    public function deductionPart(string $code): float
    {
        return match ($code) {
            // Prefer the column, fall back to the component line. NSSF is
            // charged from the employee's own flag now, so a salary set up
            // before that has the figure in the column with no line beside
            // it -- and an imported run can have the line and no column.
            // employeeNssf() already does exactly this, so it is reused
            // rather than reimplemented beside it.
            'PAYE'     => (float) $this->tax_amount ?: $this->deductionFor('PAYE'),
            'NSSF_EMP' => $this->employeeNssf(),
            default    => $this->deductionFor($code),
        };
    }

    /**
     * Whatever is deducted beyond the named parts.
     *
     * Not decoration: 122 of 2,605 live payslips carry something past the
     * statutory lines, the largest 333,333. Without it a row would not
     * reconcile and the statutory figures would look like the whole of it.
     */
    public function otherDeductions(): float
    {
        $named = 0.0;
        foreach (array_keys(self::DEDUCTION_PARTS) as $code) {
            $named += $this->deductionPart($code);
        }

        return round((float) $this->total_deductions - $named);
    }

    /**
     * Which optional parts this set of payslips actually uses.
     *
     * A column of dashes reads as a figure that was checked and found to be
     * nothing. WHT and LST are shown only where there is something to show.
     */
    public static function partsInUse($payslips): array
    {
        $parts = [];

        foreach (self::DEDUCTION_PARTS as $code => $label) {
            if (! in_array($code, self::OPTIONAL_PARTS, true)) { $parts[$code] = $label; continue; }

            foreach ($payslips as $slip) {
                if ($slip->deductionPart($code) > 0) { $parts[$code] = $label; break; }
            }
        }

        return $parts;
    }

    /**
     * Deductions are printed in the order management signed off on:
     * PAYE first, then NSSF, then everything else.  Applied on read so that
     * payslips generated before this rule was introduced print correctly too.
     */
    public static function orderComponents(array $details): array
    {
        $typeRank      = ['allowance' => 1, 'deduction' => 2, 'employer_cost' => 3];
        // WHT prints with the taxes, directly under PAYE, rather than falling in
        // among the voluntary deductions at the bottom.
        $deductionRank = ['PAYE' => 1, 'PAYE5' => 1, 'WHT' => 2, 'NSSF_EMP' => 3];

        $indexed = array_map(null, array_keys($details), $details);

        usort($indexed, function ($a, $b) use ($typeRank, $deductionRank) {
            $ta = $typeRank[$a[1]['type'] ?? ''] ?? 9;
            $tb = $typeRank[$b[1]['type'] ?? ''] ?? 9;
            if ($ta !== $tb) return $ta <=> $tb;

            $ra = $deductionRank[$a[1]['code'] ?? ''] ?? 8;
            $rb = $deductionRank[$b[1]['code'] ?? ''] ?? 8;
            if ($ra !== $rb) return $ra <=> $rb;

            return $a[0] <=> $b[0];   // keep the original order within a group
        });

        return array_column($indexed, 1);
    }

    public function orderedComponents(): array
    {
        return self::orderComponents($this->component_details ?? []);
    }

    /** NSSF employee share (5%), falling back to the stored component for legacy rows. */
    public function employeeNssf(): float
    {
        if ((float) $this->employee_nssf > 0) return (float) $this->employee_nssf;

        foreach ($this->component_details ?? [] as $d) {
            if (($d['code'] ?? '') === 'NSSF_EMP') return (float) ($d['amount'] ?? 0);
        }
        return 0.0;
    }

    /** NSSF employer share (10%) — an employer cost, never deducted from net pay. */
    public function employerNssf(): float
    {
        if ((float) $this->employer_nssf > 0) return (float) $this->employer_nssf;

        foreach ($this->component_details ?? [] as $d) {
            if (($d['code'] ?? '') === 'NSSF_CO') return (float) ($d['amount'] ?? 0);
        }
        return round($this->employeeNssf() * 2, 0);   // 10% is twice the 5% share
    }

    /** Total remitted to NSSF for this employee: 5% employee + 10% employer. */
    public function totalNssf(): float
    {
        return $this->employeeNssf() + $this->employerNssf();
    }
}
