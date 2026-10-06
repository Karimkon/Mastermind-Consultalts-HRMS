<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * The EHS Officer.
 *
 * Company Documents is now initiated by the EHS Officer, the System Admin or
 * the CEO, and there was no such role: the eleven that existed were
 * account-manager, auditor, client, employee, hr-admin, manager, md,
 * payroll-officer, quality-manager, recruiter and super-admin.
 *
 * Nobody is given it here. Who the EHS Officer is, is a decision for the
 * company, made in Roles & Permissions — a migration that picked somebody
 * would be guessing at an appointment.
 *
 * On the other two names in the instruction: "System Admin" is super-admin,
 * and "CEO" is the md role — the one person holding it is the CEO, who also
 * holds super-admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('ehs-officer', 'web');
    }

    public function down(): void
    {
        Role::where('name', 'ehs-officer')->where('guard_name', 'web')->delete();
    }
};
