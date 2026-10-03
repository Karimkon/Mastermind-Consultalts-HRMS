<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model {
    protected $fillable = [
        'title','month','year','status','client_id',
        'employment_type','hours_based','billing_total','billing_rate_override',
        'processed_by','processed_at','imported_at','import_note',
        'approved_by','approved_at',
        'hr_approved_by','hr_approved_at',
        'finance_approved_by','finance_approved_at',
        'md_approved_by','md_approved_at',
        'payment_date','notes','locked_at','locked_by',
        'payment_method','payment_reference','paid_by','paid_at',
    ];
    protected $casts = [
        'processed_at'      => 'datetime',
        'imported_at'       => 'datetime',
        'approved_at'       => 'datetime',
        'hr_approved_at'    => 'datetime',
        'finance_approved_at' => 'datetime',
        'md_approved_at'    => 'datetime',
        'locked_at'         => 'datetime',
        'paid_at'           => 'datetime',
        'payment_date'      => 'date',
    ];

    public function payslips()        { return $this->hasMany(Payslip::class); }
    public function comments()        { return $this->hasMany(PayrollComment::class)->orderBy('created_at'); }
    public function processor()       { return $this->belongsTo(User::class, 'processed_by'); }
    public function approver()        { return $this->belongsTo(User::class, 'approved_by'); }
    public function hrApprover()      { return $this->belongsTo(User::class, 'hr_approved_by'); }
    public function financeApprover() { return $this->belongsTo(User::class, 'finance_approved_by'); }
    public function mdApprover()      { return $this->belongsTo(User::class, 'md_approved_by'); }
    public function locker()          { return $this->belongsTo(User::class, 'locked_by'); }
    public function client()          { return $this->belongsTo(Client::class); }

    // Status stages: draft → processed → hr_approved → finance_approved → md_approved(locked) → paid
    public function isLocked(): bool   { return !is_null($this->locked_at); }

    /**
     * True when this run's payslips came from outside the system.
     *
     * Such a run records what was actually paid; the engine cannot reproduce
     * it, so re-processing would not correct it but overwrite it. Processing
     * is refused for as long as this is set.
     */
    public function isImported(): bool { return !is_null($this->imported_at); }
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

    /**
     * The statuses a given role is the one holding up.
     *
     * Finance appears twice on purpose: it approves after HR, and it is also the
     * stage that pays once the MD has signed off.
     */
    private const STAGE_OWNERS = [
        'hr-admin'        => ['processed'],
        'payroll-officer' => ['hr_approved', 'md_approved', 'approved'],
        'md'              => ['finance_approved'],
        'account-manager' => ['draft'],
        // The admin oversees the whole chain but is not a workflow step, so
        // the badge counts only runs that have just arrived and nobody has yet
        // acted on - the genuinely unattended ones sitting at the first review.
        // Counting every pending stage made the badge the size of the whole
        // pipeline, which told the admin nothing.
        'super-admin'     => ['processed'],
    ];

    /**
     * How many runs are waiting on this user right now.
     *
     * Approvers were never told a run had reached them, so runs queued up
     * invisibly — eighteen of them at the HR step alone. This drives the badge
     * that makes the queue visible from any page.
     */
    public static function awaitingCountFor(?\App\Models\User $user): int
    {
        if (! $user) return 0;

        $statuses = collect(self::STAGE_OWNERS)
            ->filter(fn($_, $role) => $user->hasRole($role))
            ->flatten()->unique();

        if ($statuses->isEmpty()) return 0;

        return static::whereIn('status', $statuses)->count();
    }

    /**
     * The payslips that belong in a bank/mobile-money payment instruction file.
     *
     * Withheld slips are excluded here rather than in each export: a slip that HR,
     * Finance or the MD deliberately held back must never reach the bank, and
     * repeating that filter per file is how one of them ends up missing it.
     *
     * @param  string|null  $channel  bank|mtn|airtel — omit for every channel.
     */
    public function payableSlips(?string $channel = null): \Illuminate\Support\Collection
    {
        return $this->payslips()->with('employee')->get()
            ->filter(fn ($slip) => $slip->employee !== null)
            ->filter(fn ($slip) => $slip->payment_status !== 'withheld')
            ->filter(fn ($slip) => (float) $slip->net_salary > 0)
            ->filter(fn ($slip) => $channel === null || $slip->employee->paymentChannel() === $channel)
            ->values();
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
