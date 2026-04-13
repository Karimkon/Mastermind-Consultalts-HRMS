<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['name', 'code', 'days_allowed', 'carry_forward', 'max_carry_forward', 'is_paid', 'requires_document', 'color', 'is_active'];
    protected $casts    = ['carry_forward' => 'boolean', 'is_paid' => 'boolean', 'requires_document' => 'boolean', 'is_active' => 'boolean'];

    public function requests()  { return $this->hasMany(LeaveRequest::class); }
    public function balances()  { return $this->hasMany(LeaveBalance::class); }
}