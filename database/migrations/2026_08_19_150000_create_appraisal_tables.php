<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual Balanced Score Card — one scorecard per employee per review period.
 *
 * The existing bsc_* tables hang KRAs off a shared cycle, so everyone in a cycle
 * gets the same objectives. The signed-off Excel works the other way round: a
 * supervisor picks one employee and writes that person's own KPIs, targets and
 * weights. These tables model that, and carry the routing the business asked
 * for — supervisor sets it, an appraiser fills it, it comes back to whoever
 * started it (or someone they nominate), the manager confirms, then the
 * employee self-appraises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // internal = appraised by our own account manager / line manager.
            // external = the client appraises the staff placed on their site.
            $table->string('type', 20)->default('internal');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->year('year');
            $table->string('period', 20)->nullable();          // Q1 / Q2 / Annual
            $table->date('review_from')->nullable();
            $table->date('review_to')->nullable();

            // Who set it up, who is holding it now, and who it returns to when
            // the appraiser is finished. return_to defaults to the initiator but
            // can be redirected to HR or the MD.
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('return_to_id')->nullable()->constrained('users')->nullOnDelete();

            // draft → with_appraiser → with_manager → with_employee → completed
            $table->string('status', 20)->default('draft');

            $table->decimal('overall_index', 6, 3)->nullable();   // out of 5
            $table->decimal('overall_percent', 6, 2)->nullable(); // index / 5 * 100
            $table->unsignedTinyInteger('overall_band')->nullable();

            $table->text('employee_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->timestamp('employee_signed_at')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            $table->timestamps();
            $table->index(['status', 'year']);
        });

        Schema::create('appraisal_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained()->cascadeOnDelete();
            $table->string('perspective', 30);                 // financial|customer|internal_process|learning_growth
            $table->string('kra_name');                        // the key result area
            $table->text('performance_measure')->nullable();
            $table->string('target', 50)->nullable();          // free text: "100%", "3", "85%"
            $table->string('actual_achieved', 50)->nullable();
            $table->decimal('target_percent', 8, 2)->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1–5
            $table->decimal('weightage', 6, 2)->default(0);    // % of the whole card
            $table->decimal('weighted_index', 8, 4)->nullable(); // rating × weight/100
            $table->text('evidence_note')->nullable();         // "Survey forms", "Minutes and emails"
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['appraisal_id', 'perspective']);
        });

        // Part II — mitigating factors / areas of development.
        Schema::create('appraisal_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained()->cascadeOnDelete();
            $table->text('problem_area')->nullable();
            $table->text('remedial_action')->nullable();
            $table->date('by_when')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Evidence — customer surveys, minutes, reports. Attached to the whole
        // appraisal, or to one KPI row when it backs a specific score.
        Schema::create('appraisal_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appraisal_kpi_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        // Who moved it where, and what they said — the appraisal's paper trail.
        Schema::create('appraisal_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_history');
        Schema::dropIfExists('appraisal_attachments');
        Schema::dropIfExists('appraisal_actions');
        Schema::dropIfExists('appraisal_kpis');
        Schema::dropIfExists('appraisals');
    }
};
