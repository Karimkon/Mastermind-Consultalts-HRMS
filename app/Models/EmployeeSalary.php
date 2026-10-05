<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends Model
{
    // billing_rate was missing here, so every mass assignment of it was
    // silently dropped -- the column exists and the import filled it with
    // nothing. Note that no code reads it yet: invoicing runs off the
    // client's billing_rate_multiplier, not this.
    protected $fillable = ['employee_id', 'basic_salary', 'billing_rate', 'salary_type', 'components', 'effective_from', 'effective_to', 'is_current', 'created_by'];
    protected $casts    = ['components' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
}