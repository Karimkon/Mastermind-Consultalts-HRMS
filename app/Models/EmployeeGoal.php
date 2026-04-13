<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeGoal extends Model
{
    protected $fillable = ['employee_id','cycle_id','title','description','target_date','weight','status','progress','created_by'];
    protected $casts    = ['target_date' => 'date'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function cycle()    { return $this->belongsTo(PerformanceCycle::class); }
}
