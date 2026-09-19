<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-controlled appraisal weighting.
 *
 * The perspective split is a business policy, not a constant: the Operations
 * Officer card runs Financials 30 / Customer 35 / Internal 25 / Learning 10,
 * while other roles are weighted differently. Rather than each supervisor
 * inventing that from scratch, an admin defines templates carrying the target
 * split and a starting set of KPIs, and supervisors apply one.
 *
 * The 100% ceiling still holds — templates change what the weights should be,
 * never whether they may exceed 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. "Operations Officer"
            $table->string('description')->nullable();
            $table->string('job_title')->nullable();// which role this suits

            // Target split per perspective. Stored rather than derived so an
            // admin can state the policy before any KPI exists.
            $table->decimal('financial_weight', 6, 2)->default(25);
            $table->decimal('customer_weight', 6, 2)->default(25);
            $table->decimal('internal_process_weight', 6, 2)->default(25);
            $table->decimal('learning_growth_weight', 6, 2)->default(25);

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('appraisal_template_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_template_id')->constrained()->cascadeOnDelete();
            $table->string('perspective', 30);
            $table->string('kra_name');
            $table->text('performance_measure')->nullable();
            $table->string('target', 50)->nullable();
            $table->decimal('weightage', 6, 2)->default(0);
            $table->string('evidence_note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('appraisals', function (Blueprint $table) {
            $table->foreignId('appraisal_template_id')->nullable()->after('client_id')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appraisal_template_id');
        });
        Schema::dropIfExists('appraisal_template_kpis');
        Schema::dropIfExists('appraisal_templates');
    }
};
