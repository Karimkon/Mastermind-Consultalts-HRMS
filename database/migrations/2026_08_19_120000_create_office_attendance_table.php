<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily office clock in/out for head-office staff and account managers.
 *
 * Deliberately separate from attendance_logs:
 *   - attendance_logs is keyed on employee_id and drives days worked and
 *     overtime in payroll. Account managers have no employee record, so they
 *     could never use it.
 *   - This table is keyed on user_id and is purely a record of who came to the
 *     office. Nothing here is read by PayrollService, so a missed clock-in
 *     shows up as a gap in the register without touching anyone's salary.
 *
 * Client-site visits stay in am_visit_sessions — that is the other stream.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();

            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();

            // Distance from the configured office, in metres, at each tap.
            $table->unsignedInteger('clock_in_distance_m')->nullable();
            $table->unsignedInteger('clock_out_distance_m')->nullable();

            // True when the tap was outside the office radius. Recorded, never
            // blocked — the point is to see it, not to stop it.
            $table->boolean('clock_in_offsite')->default(false);
            $table->boolean('clock_out_offsite')->default(false);

            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_attendance');
    }
};
