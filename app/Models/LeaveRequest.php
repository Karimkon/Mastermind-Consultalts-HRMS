<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days_count',
        'reason', 'status', 'approved_by', 'rejection_reason', 'document_path',
        'client_approval_required', 'client_approval_status', 'client_approved_by', 'client_actioned_at',
        'replacement_employee_id',
        'replacement_name', 'replacement_email', 'replacement_phone',
        'recalled_at', 'recalled_by', 'original_to_date', 'original_days', 'adjustment_note',
    ];
    protected $casts = [
        'from_date'               => 'date',
        'to_date'                 => 'date',
        'client_approval_required'=> 'boolean',
        'client_actioned_at'      => 'datetime',
        'recalled_at'             => 'datetime',
        'original_to_date'        => 'date',
    ];

    public function employee()       { return $this->belongsTo(Employee::class); }
    public function leaveType()      { return $this->belongsTo(LeaveType::class); }
    public function approver()       { return $this->belongsTo(Employee::class, 'approved_by'); }
    public function clientApprover() { return $this->belongsTo(Client::class, 'client_approved_by'); }

    public function recaller() { return $this->belongsTo(User::class, 'recalled_by'); }

    /**
     * Is this somebody deciding their own leave?
     *
     * HR approves everybody's leave, which quietly included their own: every
     * check asked what role you hold, never whose request it is. The HR Manager
     * granted himself two days and it was recorded as an ordinary approval,
     * with his own name in the approver column.
     *
     * Deliberately has no exemption, super-admin included. This is a separation
     * of duties rather than a question of rank — the point is that a second
     * person looks at it, and the most senior account is where that matters
     * most. Payroll already works this way: an MD cannot build the run he signs
     * off.
     */
    public function isOwnRequestOf(?User $user): bool
    {
        $employeeId = $user?->employee?->id;

        return $employeeId !== null && $employeeId === $this->employee_id;
    }

    /** Cut short or extended after it was granted. */
    public function wasAdjusted(): bool
    {
        return $this->original_to_date !== null;
    }

    /** The staff member nominated to cover, when one was picked from the register. */
    public function replacementEmployee()
    {
        return $this->belongsTo(Employee::class, 'replacement_employee_id');
    }

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