<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeClientTransfer extends Model
{
    protected $fillable = [
        'employee_id','client_id','type','effective_date','end_date','reason','recorded_by',
    ];
    protected $casts = [
        'effective_date' => 'date',
        'end_date'       => 'date',
    ];

    public function employee()   { return $this->belongsTo(Employee::class); }
    public function client()     { return $this->belongsTo(Client::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
