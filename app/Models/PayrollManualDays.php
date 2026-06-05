<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PayrollManualDays extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'days_worked', 'notes'];

    public function employee()   { return $this->belongsTo(Employee::class); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }
}
