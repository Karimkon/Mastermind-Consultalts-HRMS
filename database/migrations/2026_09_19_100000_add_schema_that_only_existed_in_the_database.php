<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema that exists in the database and in no migration.
 *
 * `ModelIntegrityTest` walks every model against a database built purely from
 * migrations, and eight things it needs were not there. All eight are present in
 * the working database, which is why nothing had ever complained:
 *
 *     employees.contract_end_date       read on every payroll run
 *     employees.termination_reason
 *     employees.retirement_date
 *     employees.retirement_reason
 *     payslips.payment_status           the whole withholding workflow
 *     payslips.withheld_reason
 *     payslips.withheld_stage
 *     payroll_comments                  every send-back reason ever written
 *
 * They were added to the live database by hand and never written down. The effect
 * is not visible day to day and is severe the moment it matters: the system cannot
 * be stood up from source. A fresh deployment, a new staging environment, a
 * restored backup rebuilt from migrations — any of those produces a database where
 * `PayrollService::processRun()` fails on the first query, payslip withholding
 * cannot be recorded, and opening a payroll run 500s on its comments.
 *
 * Definitions are copied from the live schema rather than invented, so this brings
 * a new database to the same shape as the old one rather than to a similar one.
 *
 * Every change is guarded. On the existing database this migration finds its work
 * already done and changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Read by PayrollService on every run: an employee whose contract has
            // ended is not paid. Without the column the run does not skip them —
            // it fails outright.
            if (! Schema::hasColumn('employees', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable();
            }

            if (! Schema::hasColumn('employees', 'termination_reason')) {
                $table->text('termination_reason')->nullable();
            }

            if (! Schema::hasColumn('employees', 'retirement_date')) {
                $table->date('retirement_date')->nullable();
            }

            if (! Schema::hasColumn('employees', 'retirement_reason')) {
                $table->text('retirement_reason')->nullable();
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            // The withholding workflow: HR, Finance or the MD can hold back one
            // person's pay while releasing the rest, and the stage records who did.
            if (! Schema::hasColumn('payslips', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'paid', 'withheld'])->default('pending');
            }

            if (! Schema::hasColumn('payslips', 'withheld_reason')) {
                $table->text('withheld_reason')->nullable();
            }

            if (! Schema::hasColumn('payslips', 'withheld_stage')) {
                $table->string('withheld_stage', 30)->nullable();
            }
        });

        if (! Schema::hasTable('payroll_comments')) {
            Schema::create('payroll_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');

                // Why a run moved, in the words of whoever moved it. A send-back
                // without a reason is the thing this table exists to prevent.
                $table->enum('action', ['approved', 'sent_back', 'note'])->default('note');
                $table->string('from_status', 50);
                $table->string('to_status', 50);
                $table->text('comment');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Deliberately not reversible. Dropping these would delete the approval
        // history and the withholding decisions on a database where they are the
        // only record, and this migration exists precisely because that history was
        // never protected by one.
    }
};
