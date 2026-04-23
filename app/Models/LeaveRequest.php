<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days_count',
        'reason', 'status', 'approved_by', 'rejection_reason', 'document_path',
        'client_approval_required', 'client_approval_status', 'client_approved_by', 'client_actioned_at',
    ];
    protected $casts = [
        'from_date'               => 'date',
        'to_date'                 => 'date',
        'client_approval_required'=> 'boolean',
        'client_actioned_at'      => 'datetime',
    ];

    public function employee()       { return $this->belongsTo(Employee::class); }
    public function leaveType()      { return $this->belongsTo(LeaveType::class); }
    public function approver()       { return $this->belongsTo(Employee::class, 'approved_by'); }
    public function clientApprover() { return $this->belongsTo(Client::class, 'client_approved_by'); }

    public function getStatusBadgeAttribute(): string
    {
        $map = [
            'pending'   => 'yellow',
            'approved'  => 'green',
            'rejected'  => 'red',
            'cancelled' => 'slate',
        ];
        $color = $map[$this->status] ?? 'slate';
        return '<span class="badge-' . $color . '">' . ucfirst($this->status) . '</span>';
    }
}