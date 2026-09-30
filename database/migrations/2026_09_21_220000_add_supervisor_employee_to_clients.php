<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Says, on the client record, which employee supervises the staff placed there.
 *
 * `clients.account_manager_id` already names the account manager, but it points
 * at a LOGIN, and the account managers each hold two: an @mc.ug staff account
 * tied to their Head Office employee record, and a separate @mastermind.co.ug
 * account manager account tied to nothing. Resolving a supervisor through the
 * second one found either nobody or - for Roofings and UNOC - the test record
 * MM-REVIEW-01 ("James Mugisha"), which would have become the supervisor of 457
 * people.
 *
 * Repointing employees.user_id would have fixed the lookup and broken the
 * person's ability to sign in, so the supervising employee is named here
 * instead, directly and without inference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('supervisor_employee_id')->nullable()->after('account_manager_id')
                  ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_employee_id');
        });
    }
};
