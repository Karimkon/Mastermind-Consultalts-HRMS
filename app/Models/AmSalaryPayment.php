<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmSalaryPayment extends Model
{
    protected $fillable = [
        'employee_id', 'client_id', 'account_manager_id',
        'period_month', 'period_year',
        'basic_salary', 'allowances', 'gross_salary',
        'paye_tax', 'nssf', 'other_deductions', 'total_deductions', 'net_salary',
        'payment_method', 'payment_reference', 'payment_date', 'notes',
    ];

    protected $casts = [
        'payment_date'     => 'date',
        'basic_salary'     => 'float',
        'allowances'       => 'float',
        'gross_salary'     => 'float',
        'paye_tax'         => 'float',
        'nssf'             => 'float',
        'other_deductions' => 'float',
        'total_deductions' => 'float',
        'net_salary'       => 'float',
    ];

    public function employee()    { return $this->belongsTo(Employee::class); }
    public function client()      { return $this->belongsTo(Client::class); }
    public function accountManager() { return $this->belongsTo(User::class, 'account_manager_id'); }

    public function getPeriodLabelAttribute(): string
    {
        return \Carbon\Carbon::createFromDate($this->period_year, $this->period_month, 1)->format('F Y');
    }
}
