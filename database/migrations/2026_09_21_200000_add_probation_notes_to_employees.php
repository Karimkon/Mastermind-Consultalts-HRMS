<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Probation decisions needed somewhere of their own to live.
 *
 * The reason for a decision was being appended to `employees.bio`, which is
 * rendered on the employee's profile page - so "Probation Failed: repeated
 * lateness" was shown to anyone who could open that profile. It is kept here
 * instead, beside the decision it belongs to.
 *
 * `probation_alert_sent_at` stops the advance warning being sent again every
 * day once the employee is inside the notice window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('probation_notes')->nullable()->after('probation_confirmed_by');
            $table->timestamp('probation_alert_sent_at')->nullable()->after('probation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['probation_notes', 'probation_alert_sent_at']);
        });
    }
};
