<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Name the cover person from the staff register instead of typing them.
 *
 * replacement_name / _email / _phone stay. They are the snapshot of who was
 * nominated at the time, and a leave record from 2024 should still read
 * correctly after that person leaves the company or changes their number —
 * the same reason a payslip stores its own figures rather than recomputing.
 * The new column records *which* staff member it was, so the system can
 * actually notify them and show the request on their own dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'replacement_employee_id')) {
                $table->foreignId('replacement_employee_id')
                    ->nullable()
                    ->after('reason')
                    ->constrained('employees')
                    // The nomination is history. Losing the employee record must
                    // not take the leave request with it.
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('leave_requests', 'replacement_employee_id')) {
                $table->dropForeign(['replacement_employee_id']);
                $table->dropColumn('replacement_employee_id');
            }
        });
    }
};
