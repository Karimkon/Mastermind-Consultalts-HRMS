<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmploymentHistory extends Model
{
    protected $table    = 'employment_histories';
    protected $fillable = ['employee_id', 'position', 'department', 'start_date', 'end_date', 'reason'];
    public function employee() { return $this->belongsTo(Employee::class); }
}