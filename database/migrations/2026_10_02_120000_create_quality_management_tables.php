<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Management - the control layer that sits over the whole HRMS.
 *
 * Nothing here duplicates HR data. Every finding points back at the record it
 * is about through a polymorphic (subject_type, subject_id) pair, so a quality
 * issue on an employee, a payslip or a payroll run is linked, never copied.
 */
return new class extends Migration {
    public function up(): void
    {
        // What "good" looks like: the expectations the engine measures against.
        Schema::create('quality_standards', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();              // e.g. QS-EMP-01
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('hr_function')->index();        // employee_central, payroll, recruitment, attendance, leave, performance, compliance
            $t->string('category')->default('data_quality'); // data_quality, compliance, process, accuracy
            $t->string('severity')->default('medium'); // low, medium, high, critical
            $t->unsignedInteger('weight')->default(10); // relative weight in the score
            $t->unsignedTinyInteger('target_score')->default(100); // % expected to pass
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // The automated checks the engine runs. engine_key maps to a routine in
        // the QualityEngine; a check may or may not belong to a standard.
        Schema::create('quality_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_standard_id')->nullable()->constrained()->nullOnDelete();
            $t->string('engine_key')->unique();        // employee.missing_nssf, payroll.negative_net ...
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('hr_function')->index();
            $t->string('severity')->default('medium');
            $t->unsignedInteger('weight')->default(10);
            $t->boolean('is_automated')->default(true);
            $t->boolean('auto_raise_nc')->default(true); // raise a non-conformity when it fails
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
        });

        // One execution of the engine (a batch of checks).
        Schema::create('quality_check_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('trigger')->default('manual');  // manual, scheduled
            $t->string('scope')->default('all');       // all, or an hr_function
            $t->string('status')->default('running');  // running, completed, failed
            $t->unsignedInteger('checks_run')->default(0);
            $t->unsignedInteger('subjects_scanned')->default(0);
            $t->unsignedInteger('passed')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->unsignedInteger('warnings')->default(0);
            $t->unsignedInteger('nonconformities_raised')->default(0);
            $t->decimal('score', 5, 2)->nullable();    // overall quality score for this run
            $t->json('summary')->nullable();           // per-function breakdown
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        // Every individual finding in a run. Fails point at the offending record.
        Schema::create('quality_check_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_check_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('quality_check_id')->constrained()->cascadeOnDelete();
            $t->string('status');                      // pass, fail, warning
            $t->nullableMorphs('subject');             // the employee / payslip / run it is about
            $t->string('subject_label')->nullable();   // human name, so the result reads without a join
            $t->text('message')->nullable();
            $t->json('context')->nullable();
            $t->timestamps();
            $t->index(['status', 'quality_check_id']);
        });

        // Issues raised from failed checks, audits, or by hand. The thing that
        // gets worked and closed.
        Schema::create('quality_nonconformities', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();         // NC-000123
            $t->foreignId('quality_check_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quality_check_result_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('quality_audit_id')->nullable();
            $t->nullableMorphs('subject');
            $t->string('subject_label')->nullable();
            $t->string('hr_function')->index();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('severity')->default('medium'); // low, medium, high, critical
            $t->string('source')->default('auto');     // auto, manual, audit
            $t->string('status')->default('open');     // open, investigating, resolved, closed, risk_accepted
            $t->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->nullable();
            $t->timestamp('detected_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->text('resolution_notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'severity']);
        });

        // Corrective AND preventive actions (CAPA) against a non-conformity.
        Schema::create('quality_corrective_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_nonconformity_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('corrective'); // corrective, preventive
            $t->text('action');
            $t->text('root_cause')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('planned');  // planned, in_progress, completed, verified, cancelled
            $t->date('due_date')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('effectiveness_notes')->nullable();
            $t->timestamps();
        });

        // Audits - a planned review of a function against the standards.
        Schema::create('quality_audits', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();         // QA-0007
            $t->string('title');
            $t->string('scope')->default('all');       // all or an hr_function
            $t->string('type')->default('internal');   // internal, external, process
            $t->string('status')->default('planned');  // planned, in_progress, completed
            $t->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->date('planned_date')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->decimal('score', 5, 2)->nullable();
            $t->text('summary')->nullable();
            $t->timestamps();
        });

        // The line items of an audit - a question scored against a standard.
        Schema::create('quality_audit_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_audit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('quality_standard_id')->nullable()->constrained()->nullOnDelete();
            $t->text('question');
            $t->string('result')->nullable();          // conform, minor_nc, major_nc, observation, na
            $t->text('notes')->nullable();
            $t->text('evidence')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_audit_items');
        Schema::dropIfExists('quality_audits');
        Schema::dropIfExists('quality_corrective_actions');
        Schema::dropIfExists('quality_nonconformities');
        Schema::dropIfExists('quality_check_results');
        Schema::dropIfExists('quality_check_runs');
        Schema::dropIfExists('quality_checks');
        Schema::dropIfExists('quality_standards');
    }
};
