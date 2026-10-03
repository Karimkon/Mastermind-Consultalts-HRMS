<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a payroll run whose payslips were loaded from outside the system.
 *
 * Runs 25 and 27–30 were written straight into the database on 2026-09-21 by a
 * script that is in neither this repository nor its history. Their payslips
 * carry component codes the engine cannot produce (LOP_INFO, PAYE_INFO,
 * OTHER_DED, ADDITION, MIDMONTH, ADVANCE) and follow rules it does not
 * implement — NSSF on 70% of gross on one client, PAYE recorded but not
 * withheld on another, no deductions at all on a third.
 *
 * Nothing stopped somebody pressing Process on them: the web controller only
 * refuses once a run reaches hr_approved, and all five sit at 'processed'. One
 * click would have replaced 469 payslips worth UGX 132.9m of real payments with
 * figures computed from today's salary records. This is the flag that refuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->timestamp('imported_at')->nullable()->after('processed_at');
            $table->string('import_note', 255)->nullable()->after('imported_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['imported_at', 'import_note']);
        });
    }
};
