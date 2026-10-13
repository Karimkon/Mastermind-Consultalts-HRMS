<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One spell holding one role.
 *
 * An open row (ended_at null) means they hold it now. A closed one means they
 * used to. Several rows per person per role are expected: somebody can hold a
 * post, leave it, and come back to it.
 */
class RoleAssignment extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'employee_id', 'role_id', 'role_name',
        'started_at', 'ended_at', 'assigned_by', 'ended_by', 'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who this was, whether or not the login still exists.
     *
     * Read this rather than ->user->name wherever it is displayed: the account
     * may have been deleted, and this row outliving it is the point.
     */
    public function holderName(): string
    {
        return $this->user?->name ?? $this->user_name ?? 'Account since deleted';
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedBy()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeFormer($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /** "Mar 2024 – present", or "unknown – Oct 2026" where the start was never recorded. */
    public function periodLabel(): string
    {
        $from = $this->started_at?->format('M Y') ?? 'unknown';
        $to = $this->ended_at?->format('M Y') ?? 'present';

        return $from.' – '.$to;
    }
}
