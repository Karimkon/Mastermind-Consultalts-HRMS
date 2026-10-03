<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Head office staff work wherever the clients are.
 *
 * Attendance measures a clock-in against the premises of the employee's own
 * client. That is right for somebody posted to one site, and wrong for the HQ
 * staff who spend their week visiting clients: every visit was recorded as
 * "outside the fence" and flagged to HR, because it was measured against head
 * office while they were standing at a client.
 *
 * `is_head_office` marks the clients whose staff roam. A flag rather than
 * matching the company name, because the name is display text - renaming the
 * client would silently change how payroll-feeding attendance behaves, with
 * nothing to point at.
 *
 * `verified_at_client_id` records WHICH premises the fix was verified against,
 * so an HQ person clocking in at a client reads as what it is. The employing
 * client stays in `client_id`, untouched, because payroll keys on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_head_office')->default(false)->after('attendance_enabled');
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('verified_at_client_id')->nullable()->after('client_id')
                ->constrained('clients')->nullOnDelete();
        });

        // Mark the existing head office. Matched on the name here, once, rather
        // than relying on the name forever.
        DB::table('clients')
            ->where('company_name', 'like', '%Mastermind%')
            ->where(function ($q) {
                $q->where('company_name', 'like', '%HQ%')
                  ->orWhere('company_name', 'like', '%Head Office%');
            })
            ->update(['is_head_office' => true]);
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_at_client_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('is_head_office');
        });
    }
};
