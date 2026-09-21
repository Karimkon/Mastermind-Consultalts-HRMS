<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A change an account manager has asked for, awaiting HR.
 *
 * Nothing here has happened yet. The record it refers to still holds its old
 * values until somebody with HR authority approves this row.
 */
class PendingChange extends Model
{
    // NOTE: this column is `original_values`, not `original`.
    //
    // Eloquent\Model already has a protected $original holding the raw
    // attribute snapshot. A column called `original` reads correctly from
    // outside the class, where __get() runs, and silently returns the model's
    // internal array from inside it — so $this->original['bank_account'] was
    // null while $pending->original showed the right JSON.

    protected $fillable = [
        'requested_by', 'action', 'model_type', 'model_id', 'label',
        'payload', 'original_values', 'status', 'reviewed_by', 'reviewed_at',
        'review_note', 'client_id',
    ];

    protected $casts = [
        'payload'     => 'array',
        'original_values'    => 'array',
        'reviewed_at' => 'datetime',
    ];

    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer()  { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function client()    { return $this->belongsTo(Client::class); }

    public function scopePending($query) { return $query->where('status', self::PENDING); }

    public function isPending(): bool { return $this->status === self::PENDING; }

    /** The model this change is about, or null if it has since been deleted. */
    public function subject(): ?Model
    {
        $class = '\\App\\Models\\' . $this->model_type;

        if (! $this->model_id || ! class_exists($class)) {
            return null;
        }

        return $class::find($this->model_id);
    }

    /**
     * Field by field: what it was, what it would become, and whether somebody
     * else has moved it since this was asked for.
     *
     * That last part matters. A change approved a week late must not quietly
     * undo an edit made in between, so a field whose current value no longer
     * matches what the requester saw is flagged rather than applied blindly.
     */
    public function diff(): array
    {
        $subject = $this->subject();
        $rows = [];

        foreach ($this->payload as $field => $proposed) {
            $was = $this->original_values[$field] ?? null;
            $now = $subject ? $subject->getAttribute($field) : $was;

            $rows[] = [
                'field'    => $field,
                'was'      => $was,
                'proposed' => $proposed,
                'current'  => $now,
                // Same comparison the capture used, so a value that was not an
                // edit going in is not a conflict coming out.
                'drifted'  => ! app(\App\Services\ChangeRequestService::class)->sameValue($now, $was),
            ];
        }

        return $rows;
    }

    public function hasDrift(): bool
    {
        foreach ($this->diff() as $row) {
            if ($row['drifted']) {
                return true;
            }
        }

        return false;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            default        => 'yellow',
        };
    }
}
