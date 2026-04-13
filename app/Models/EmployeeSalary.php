<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends Model
{
    protected $fillable = ['employee_id', 'basic_salary', 'components', 'effective_from', 'effective_to', 'is_current', 'created_by'];
    protected $casts    = ['components' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
}