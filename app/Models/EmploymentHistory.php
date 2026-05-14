<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmploymentHistory extends Model
{
    protected $table    = 'employment_histories';
    protected $fillable = ['employee_id', 'position', 'company_name', 'department_id', 'start_date', 'end_date', 'reason_for_change', 'type', 'recorded_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function employee()   { return $this->belongsTo(Employee::class); }
    public function department() { return $this->belongsTo(\App\Models\Department::class); }
}