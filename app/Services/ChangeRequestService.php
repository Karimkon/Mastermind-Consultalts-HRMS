<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PendingChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Proposing a change instead of making one.
 *
 * An account manager's edits to the people they place are held here until HR
 * approves them. Every step — asking, approving, refusing — writes an audit
 * row, so the record of who wanted what and who allowed it survives the change
 * itself.
 */
class ChangeRequestService
{
    /**
     * Record a wanted change. Returns null when nothing would actually change.
     *
     * Only fields that genuinely differ are stored. A form posts forty inputs
     * and a reviewer should be shown the two that moved, not asked to read
     * forty rows of which thirty-eight are identical.
     */
    public function capture(
        Model $model,
        array $proposed,
        ?string $label = null,
        ?int $clientId = null,
        string $action = 'update'
    ): ?PendingChange {
        $changes = [];
        $original = [];

        foreach ($proposed as $field => $value) {
            $current = $model->getAttribute($field);

            if ($this->sameValue($current, $value)) {
                continue;
            }

            $changes[$field] = $value;
            $original[$field] = $current;
        }

        if (! $changes) {
            return null;
        }

        $pending = PendingChange::create([
            'requested_by' => auth()->id(),
            'action'       => $action,
            'model_type'   => class_basename($model),
            'model_id'     => $model->getKey(),
            'label'        => $label,
            'payload'      => $changes,
            'original_values'     => $original,
            'status'       => PendingChange::PENDING,
            'client_id'    => $clientId,
        ]);

        $this->audit('change_requested', $pending, $original, $changes);
        $this->tellHr($pending);

        return $pending;
    }

    /**
     * Whether two values say the same thing.
     *
     * Compared loosely on purpose. A decimal column reads back as "1500.00"
     * where the form sent "1500"; a cast boolean reads back as false where the
     * form sent 0. Neither is an edit, and treating them as one would put a
     * change in front of HR on every save that touched nothing.
     *
     * Booleans are handled before the string comparison because (string) false
     * is "" while (string) 0 is "0" - the two would never match.
     */
    public function sameValue($a, $b): bool
    {
        $boolish = fn ($v) => is_bool($v)
            || $v === 0 || $v === 1 || $v === '0' || $v === '1';

        if ((is_bool($a) || is_bool($b)) && $boolish($a) && $boolish($b)) {
            return (int) $a === (int) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return (string) $a === (string) $b;
    }

    /** Write the change for real. */
    public function approve(PendingChange $pending, ?string $note = null): bool
    {
        if (! $pending->isPending()) {
            return false;
        }

        $subject = $pending->subject();

        if (! $subject) {
            // The record went away while this sat in the queue. Refusing is the
            // honest outcome; recreating it from a diff would invent a record.
            $pending->update([
                'status'      => PendingChange::REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => 'The record no longer exists.',
            ]);

            return false;
        }

        DB::transaction(function () use ($pending, $subject, $note) {
            // The model's own observer writes the 'updated' audit row, so the
            // applied change is recorded twice over: once as the decision, once
            // as the write.
            $subject->fill($pending->payload)->save();

            $pending->update([
                'status'      => PendingChange::APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->audit('change_approved', $pending, $pending->original_values, $pending->payload);
            $this->tellRequester($pending, true, $note);
        });

        return true;
    }

    public function reject(PendingChange $pending, ?string $note = null): bool
    {
        if (! $pending->isPending()) {
            return false;
        }

        $pending->update([
            'status'      => PendingChange::REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->audit('change_rejected', $pending, $pending->original_values, $pending->payload);
        $this->tellRequester($pending, false, $note);

        return true;
    }

    private function audit(string $action, PendingChange $pending, array $old, array $new): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => $pending->model_type,
            'model_id'   => $pending->model_id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($new + ['_pending_change_id' => $pending->id]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function tellHr(PendingChange $pending): void
    {
        $what = $pending->label ?: ($pending->model_type.' #'.$pending->model_id);
        $who = $pending->requester?->name ?? 'An account manager';
        $count = count($pending->payload);

        // Spatie throws on a role name this installation does not have.
        $roles = \Spatie\Permission\Models\Role::whereIn('name', ['hr-admin', 'super-admin'])->pluck('name')->all();
        if (! $roles) return;

        User::role($roles)->get()->unique('id')->each(function (User $u) use ($what, $who, $count, $pending) {
            Notification::create([
                'user_id' => $u->id,
                'type'    => 'change_requested',
                'title'   => 'A change is waiting for approval',
                'body'    => "{$who} wants to change {$count} field(s) on {$what}.",
                'data'    => ['pending_change_id' => $pending->id],
            ]);
        });
    }

    private function tellRequester(PendingChange $pending, bool $approved, ?string $note): void
    {
        if (! $pending->requested_by) {
            return;
        }

        $what = $pending->label ?: ($pending->model_type.' #'.$pending->model_id);

        Notification::create([
            'user_id' => $pending->requested_by,
            'type'    => $approved ? 'change_approved' : 'change_rejected',
            'title'   => $approved ? 'Your change was approved' : 'Your change was not approved',
            'body'    => "Your edit to {$what} was ".($approved ? 'applied' : 'declined')
                . ($note ? ": {$note}" : '.'),
            'data'    => ['pending_change_id' => $pending->id],
        ]);
    }
}
