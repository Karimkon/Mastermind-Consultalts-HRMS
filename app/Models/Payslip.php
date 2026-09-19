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
