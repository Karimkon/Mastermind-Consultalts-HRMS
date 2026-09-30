<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The annual training plan.
 *
 * `training_courses` is a catalogue - what can be taught. It has no date, no
 * venue, no trainer and no attendee list, so it cannot answer the question the
 * business actually asks: who is being trained, when, where, by whom and at what
 * cost. A session is one scheduled run of a course, and the year's sessions are
 * the plan.
 *
 * The cost split mirrors the SOGEA sheet this was modelled on: pedagogic (the
 * trainer's fee), logistic (travel, venue, materials), remuneration (paid time
 * off the job) and the company's own logistic cost. They are kept apart because
 * finance codes them differently, and summed for the plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();

            // A session usually runs a catalogue course, but a one-off external
            // course should not require polluting the catalogue first.
            $table->foreignId('training_course_id')->nullable()
                  ->constrained('training_courses')->nullOnDelete();
            $table->string('title');
            $table->string('category', 100)->nullable();       // "Training Area" on the sheet
            $table->enum('delivery', ['classroom', 'e_learning', 'on_the_job', 'external'])
                  ->default('classroom');

            // ── When ──────────────────────────────────────────────────────────
            $table->unsignedSmallInteger('plan_year');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('duration_days', 6, 2)->default(0);
            $table->decimal('duration_hours', 6, 2)->default(0);

            // ── Where and by whom ────────────────────────────────────────────
            $table->string('venue')->nullable();
            $table->string('trainer')->nullable();             // the person delivering it
            $table->string('provider')->nullable();            // the training company
            $table->unsignedSmallInteger('max_participants')->nullable();

            // ── Cost ─────────────────────────────────────────────────────────
            $table->decimal('cost_pedagogic', 14, 2)->default(0);
            $table->decimal('cost_logistic', 14, 2)->default(0);
            $table->decimal('cost_remuneration', 14, 2)->default(0);
            $table->decimal('cost_company', 14, 2)->default(0);

            // ── Approval: initiator -> HR -> CEO ─────────────────────────────
            $table->enum('status', [
                'draft', 'pending_hr', 'pending_ceo', 'approved',
                'rejected', 'completed', 'cancelled',
            ])->default('draft');

            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hr_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('ceo_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ceo_approved_at')->nullable();
            $table->text('decision_note')->nullable();          // why it was sent back or refused

            $table->text('justification')->nullable();          // why this training is needed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plan_year', 'status']);
            $table->index('starts_on');
        });

        Schema::create('training_session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Who put them forward, and why. A recommendation coming out of an
            // appraisal carries the appraisal it came from.
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appraisal_id')->nullable()->constrained('appraisals')->nullOnDelete();
            $table->text('reason')->nullable();

            $table->enum('attendance', ['nominated', 'confirmed', 'attended', 'absent', 'withdrawn'])
                  ->default('nominated');
            $table->date('completed_on')->nullable();
            $table->decimal('score', 5, 2)->nullable();

            // ── Return on training ───────────────────────────────────────────
            // The honest measure of whether it worked: one thing the person does,
            // timed or counted before and after. "Builds an Excel report" at 120
            // minutes before and 30 after is a 75% improvement that can be set
            // against what the seat cost.
            $table->string('roi_metric')->nullable();           // what was measured
            $table->string('roi_unit', 40)->nullable();         // minutes, units/hour, errors
            $table->decimal('roi_before', 14, 2)->nullable();
            $table->decimal('roi_after', 14, 2)->nullable();
            // Lower is better for a time; higher is better for output. Without
            // this the same numbers mean the opposite thing.
            $table->boolean('roi_lower_is_better')->default(true);
            $table->date('roi_measured_on')->nullable();
            $table->text('roi_note')->nullable();

            $table->timestamps();
            // Named explicitly: the generated name would be 66 characters and
            // MySQL caps identifiers at 64.
            $table->unique(['training_session_id', 'employee_id'], 'tsp_session_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_participants');
        Schema::dropIfExists('training_sessions');
    }
};
