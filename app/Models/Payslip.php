<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'basic_salary', 'total_allowances', 'gross_salary', 'total_deductions', 'tax_amount', 'net_salary', 'worked_days', 'absent_days', 'component_details'];
    protected $casts    = ['component_details' => 'array'];
    public function employee()   { return $this->belongsTo(Employee::class); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }
}