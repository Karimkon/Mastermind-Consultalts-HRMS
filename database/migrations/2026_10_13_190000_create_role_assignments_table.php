<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who has held which post, and when.
 *
 * model_has_roles records only who holds a role right now: three columns, no
 * dates, and a removal deletes the row. So there is no way to answer "who was
 * HR before Ian", and the audit log cannot answer it either — Spatie writes
 * straight to the pivot rather than through a model, so none of its 4,084 rows
 * mention a role.
 *
 * This is an appointment record, deliberately NOT the source of truth for
 * access. The pivot still decides what somebody can do, because every request
 * checks it; this table records what happened. Making history authoritative
 * would mean fighting the permission library on every gate check for the sake
 * of a report.
 *
 * Current holders are backfilled so the archive does not start empty. Their
 * start date is left NULL rather than invented — nothing in the system knows
 * when Ian became HR, and a made-up date in an audit trail is worse than an
 * admitted gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();

            // Indexed, but deliberately NOT a foreign key. Users are hard
            // deleted -- App\Models\User has no SoftDeletes -- so a cascade here
            // would erase a departed HR's history the moment somebody tidies up
            // their login, which is exactly the record this table exists to
            // keep. Restricting instead would make user deletion fail, breaking
            // something that works today.
            $table->unsignedBigInteger('user_id')->index();

            // The name as it was at the time, so a row still reads sensibly
            // after the account behind it is gone.
            $table->string('user_name')->nullable();

            // The person, where we know them. Roles hang off a login, but the
            // archive is about people: 312 employees have no login yet, and an
            // employee can outlive the account they were given.
            $table->unsignedBigInteger('employee_id')->nullable();

            // Same reasoning, and role_name below makes it explicit: the point
            // of denormalising the name is to outlive the role, which a
            // cascading delete would defeat.
            $table->unsignedBigInteger('role_id')->nullable()->index();

            // Denormalised on purpose. A role can be renamed or deleted, and the
            // record of who held it must survive that.
            $table->string('role_name');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();

            $table->string('reason', 500)->nullable();

            $table->timestamps();

            // "Who holds this now" and "who has ever held it" are the two
            // questions this table exists to answer.
            $table->index(['role_name', 'ended_at']);
            $table->index(['user_id', 'ended_at']);
            $table->index('employee_id');
        });

        // ---- backfill, so the archive opens with the truth as it stands today
        $now = now();

        $current = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->select('model_has_roles.model_id', 'roles.id as role_id', 'roles.name', 'users.name as user_name')
            ->get();

        // One query rather than one per row: there are 1,270 live role rows on
        // the live database, and a migration should not sit there issuing 1,270
        // lookups on a shared host.
        $employeeByUser = DB::table('employees')
            ->whereNotNull('user_id')
            ->pluck('id', 'user_id');

        $rows = [];

        foreach ($current as $r) {
            $rows[] = [
                'user_id' => $r->model_id,
                'user_name' => $r->user_name,
                'employee_id' => $employeeByUser[$r->model_id] ?? null,
                'role_id' => $r->role_id,
                'role_name' => $r->name,
                // Unknown, and said so. See the note above.
                'started_at' => null,
                'ended_at' => null,
                'assigned_by' => null,
                'reason' => 'Recorded when the role archive was introduced; the original appointment date is not held anywhere.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('role_assignments')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
