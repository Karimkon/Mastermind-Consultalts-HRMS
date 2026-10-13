<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Changes a user's roles AND records the change.
 *
 * Everything that alters who holds a role should come through here, because the
 * two stores have to agree: Spatie's pivot decides what somebody can do on the
 * next request, and role_assignments records what happened. A controller that
 * calls assignRole() directly leaves a hole in the archive that nothing can
 * reconstruct afterwards.
 *
 * Note what this deliberately does NOT do: it never changes how access is
 * decided. syncRoles is still called exactly as before, so every gate, every
 *
 * @role directive and every scope — account managers reaching their clients
 * included — behaves identically. The history is written beside the change,
 * never in place of it.
 */
class RoleAssignments
{
    /**
     * Set a user's roles to exactly this list, recording what opened and closed.
     *
     * @param  array<int, string>  $roleNames
     */
    public function sync(User $user, array $roleNames, ?string $reason = null): void
    {
        $before = $user->roles->pluck('name')->all();
        $after = array_values(array_unique($roleNames));

        DB::transaction(function () use ($user, $after, $before, $reason) {
            // The access change itself, unchanged from what the controllers did
            // before this service existed.
            $user->syncRoles($after);

            foreach (array_diff($after, $before) as $granted) {
                $this->open($user, $granted, $reason);
            }

            foreach (array_diff($before, $after) as $revoked) {
                $this->close($user, $revoked, $reason);
            }
        });
    }

    /** Add one role, leaving the others alone. */
    public function grant(User $user, string $roleName, ?string $reason = null): void
    {
        if ($user->hasRole($roleName)) {
            return;
        }

        DB::transaction(function () use ($user, $roleName, $reason) {
            $user->assignRole($roleName);
            $this->open($user, $roleName, $reason);
        });
    }

    /** Take one role away, leaving the others alone. */
    public function revoke(User $user, string $roleName, ?string $reason = null): void
    {
        if (! $user->hasRole($roleName)) {
            return;
        }

        DB::transaction(function () use ($user, $roleName, $reason) {
            $user->removeRole($roleName);
            $this->close($user, $roleName, $reason);
        });
    }

    /**
     * Everyone who has ever held a role, most recent first.
     *
     * This is the question the whole table exists for: how many HRs have we
     * had, and who were they.
     */
    public function holdersOf(string $roleName)
    {
        return RoleAssignment::with(['user', 'employee', 'assignedBy'])
            ->where('role_name', $roleName)
            ->orderByRaw('ended_at IS NOT NULL')   // current first
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Every post one person has held. */
    public function historyFor(User $user)
    {
        return RoleAssignment::with('assignedBy', 'endedBy')
            ->where('user_id', $user->id)
            ->orderByRaw('ended_at IS NOT NULL')
            ->orderByDesc('id')
            ->get();
    }

    // ───────────────────────────── internals ─────────────────────────────

    private function open(User $user, string $roleName, ?string $reason): void
    {
        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            return;
        }

        // Guard against a duplicate open spell if something grants twice.
        $already = RoleAssignment::where('user_id', $user->id)
            ->where('role_name', $roleName)
            ->whereNull('ended_at')
            ->exists();

        if ($already) {
            return;
        }

        RoleAssignment::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'employee_id' => Employee::where('user_id', $user->id)->value('id'),
            'role_id' => $role->id,
            'role_name' => $roleName,
            'started_at' => now(),
            'assigned_by' => auth()->id(),
            'reason' => $reason,
        ]);
    }

    private function close(User $user, string $roleName, ?string $reason): void
    {
        // Row by row rather than a mass update, because the leaving note is
        // appended to whatever the appointment reason was. Doing that in SQL
        // needs CONCAT, which MariaDB has and SQLite does not -- so the suite
        // would be testing a statement production never runs.
        $open = RoleAssignment::where('user_id', $user->id)
            ->where('role_name', $roleName)
            ->whereNull('ended_at')
            ->get();

        foreach ($open as $assignment) {
            $assignment->ended_at = now();
            $assignment->ended_by = auth()->id();

            // Keep the appointment reason if there was one; a leaving note
            // should not overwrite why they were given the post.
            if ($reason) {
                $assignment->reason = $assignment->reason
                    ? $assignment->reason.' | Ended: '.$reason
                    : 'Ended: '.$reason;
            }

            $assignment->save();
        }
    }
}
