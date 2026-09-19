<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime must be approved by HR or an admin before payroll pays it.
 *
 * Until now PayrollService summed the raw overtime_hours written at clock-out,
 * so whatever the clock produced was paid at 1.5x with nobody signing it off.
 * The raw figure is kept untouched as the record of what the clock saw;
 * approved_overtime_hours is the figure payroll is allowed to use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->decimal('approved_overtime_hours', 5, 2)->default(0)->after('overtime_hours');
            $table->string('overtime_status', 20)->default('pending')->after('approved_overtime_hours');
            $table->foreignId('overtime_approved_by')->nullable()->after('overtime_status')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_approved_at')->nullable()->after('overtime_approved_by');
            $table->string('overtime_note')->nullable()->after('overtime_approved_at');

            $table->index(['overtime_status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['overtime_approved_by']);
            $table->dropIndex(['overtime_status', 'date']);
            $table->dropColumn([
                'approved_overtime_hours', 'overtime_status',
                'overtime_approved_by', 'overtime_approved_at', 'overtime_note',
            ]);
        });
    }
};
