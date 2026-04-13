<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ExitWorkflow extends Model
{
    protected $fillable = ['employee_id','exit_date','reason','interview_notes','equipment_returned','equipment_notes','final_settlement_done','settlement_amount','clearance_done','status','initiated_by'];
    protected $casts    = ['exit_date' => 'date', 'equipment_returned' => 'boolean', 'final_settlement_done' => 'boolean', 'clearance_done' => 'boolean'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function initiator() { return $this->belongsTo(User::class, 'initiated_by'); }
}
