<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeKpi extends Model
{
    protected $table    = 'employee_kpis';
    protected $fillable = ['employee_id', 'kpi_id', 'cycle_id', 'target', 'actual', 'score'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function kpi()      { return $this->belongsTo(Kpi::class); }
    public function cycle()    { return $this->belongsTo(PerformanceCycle::class); }
}