<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model {
    protected $fillable = [
        'title','month','year','status','client_id',
        'processed_by','processed_at','approved_by','approved_at',
        'hr_approved_by','hr_approved_at',
        'finance_approved_by','finance_approved_at',
        'md_approved_by','md_approved_at',
        'payment_date','notes','locked_at','locked_by',
        'payment_method','payment_reference','paid_by','paid_at',
    ];
    protected $casts = [
        'processed_at'      => 'datetime',
        'approved_at'       => 'datetime',
        'hr_approved_at'    => 'datetime',
        'finance_approved_at' => 'datetime',
        'md_approved_at'    => 'datetime',
        'locked_at'         => 'datetime',
        'paid_at'           => 'datetime',
        'payment_date'      => 'date',
    ];

    public function payslips()        { return $this->hasMany(Payslip::class); }
    public function processor()       { return $this->belongsTo(User::class, 'processed_by'); }
    public function approver()        { return $this->belongsTo(User::class, 'approved_by'); }
    public function hrApprover()      { return $this->belongsTo(User::class, 'hr_approved_by'); }
    public function financeApprover() { return $this->belongsTo(User::class, 'finance_approved_by'); }
    public function mdApprover()      { return $this->belongsTo(User::class, 'md_approved_by'); }
    public function locker()          { return $this->belongsTo(User::class, 'locked_by'); }
    public function client()          { return $this->belongsTo(Client::class); }

    // Status stages: draft → processed → hr_approved → finance_approved → md_approved(locked) → paid
    public function isLocked(): bool   { return !is_null($this->locked_at); }
    public function isEditable(): bool { return !$this->isLocked() && !in_array($this->status, ['paid']); }

    public function workflowStage(): int
    {
        return match($this->status) {
            'draft'            => 0,
            'processing'       => 1,
            'processed'        => 1,
            'hr_approved'      => 2,
            'finance_approved' => 3,
            'md_approved'      => 4,
            'approved'         => 4,
            'paid'             => 5,
            default            => 0,
        };
    }

    public function getStatusBadgeAttribute(): string {
        if ($this->isLocked()) {
            return '<span class="badge-red"><i class="fas fa-lock mr-1"></i>Locked</span>';
        }
        return match($this->status) {
            'draft'            => '<span class="badge-gray">Draft</span>',
            'processing'       => '<span class="badge-yellow">Processing</span>',
            'processed'        => '<span class="badge-blue">Submitted to HR</span>',
            'hr_approved'      => '<span class="badge-blue">HR Approved</span>',
            'finance_approved' => '<span class="badge-purple">Finance Approved</span>',
            'md_approved'      => '<span class="badge-green"><i class="fas fa-check mr-1"></i>MD Approved</span>',
            'approved'         => '<span class="badge-green">Approved</span>',
            'paid'             => '<span class="badge-green"><i class="fas fa-check mr-1"></i>Paid</span>',
            default            => '',
        };
    }
}
