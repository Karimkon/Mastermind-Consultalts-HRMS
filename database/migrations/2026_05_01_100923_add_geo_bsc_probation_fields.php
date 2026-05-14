<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        //  PHASE 2 — GEO LOCATION
        // ─────────────────────────────────────────────

        // Add client_id + clock-out coordinates to attendance_logs
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('lng');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
            $table->decimal('distance_metres', 8, 2)->nullable()->after('clock_out_lng');
        });

        // AM visit sessions — one per client site visit
        Schema::create('am_visit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // AM user
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat_in',  10, 7)->nullable();
            $table->decimal('lng_in',  10, 7)->nullable();
            $table->decimal('lat_out', 10, 7)->nullable();
            $table->decimal('lng_out', 10, 7)->nullable();
            $table->dateTime('clocked_in_at');
            $table->dateTime('clocked_out_at')->nullable();
            $table->string('site_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        //  PHASE 3 — BALANCED SCORECARD
        // ─────────────────────────────────────────────

        // BSC Cycles (appraisal periods)
        Schema::create('bsc_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. "Q1 2025"
            $table->year('year');
            $table->string('period')->nullable();          // Q1 / Q2 / Annual
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // BSC KRAs (Key Result Areas per perspective)
        Schema::create('bsc_kras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('bsc_cycles')->cascadeOnDelete();
            $table->enum('perspective', [
                'financial',
                'customer',
                'internal_process',
                'learning_growth',
            ]);
            $table->string('kra_name');
            $table->string('objective')->nullable();
            $table->string('measure')->nullable();
            $table->decimal('target', 12, 2)->nullable();
            $table->string('unit')->default('%');           // %, amount, count, etc.
            $table->decimal('weightage', 5, 2)->default(0); // % weight within perspective
            $table->enum('review_frequency', ['monthly', 'quarterly', 'annual'])->default('quarterly');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // BSC Entries (per employee per KRA)
        Schema::create('bsc_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kra_id')->constrained('bsc_kras')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appraiser_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('appraiser_role', ['admin', 'hr', 'account_manager', 'self'])->default('account_manager');
            $table->decimal('actual_achieved', 12, 2)->nullable();
            $table->decimal('target_percent',   6, 2)->nullable(); // actual/target * 100
            $table->unsignedTinyInteger('rating')->nullable();     // 1–5
            $table->decimal('weighted_index', 6, 4)->nullable();   // rating × weight
            $table->text('employee_comment')->nullable();
            $table->text('appraiser_comment')->nullable();
            $table->text('problem_areas')->nullable();
            $table->text('remedial_actions')->nullable();
            $table->date('remedial_by_when')->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'approved'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['kra_id', 'employee_id', 'appraiser_role']);
        });

        // ─────────────────────────────────────────────
        //  PHASE 4 — PROBATION + DOCUMENT EXPIRY ALERTS
        // ─────────────────────────────────────────────

        // Probation columns on employees
        Schema::table('employees', function (Blueprint $table) {
            $table->date('probation_end_date')->nullable()->after('hire_date');
            $table->enum('probation_status', ['on_probation', 'passed', 'failed', 'extended'])
                  ->nullable()->after('probation_end_date');
            $table->timestamp('probation_confirmed_at')->nullable()->after('probation_status');
            $table->foreignId('probation_confirmed_by')->nullable()
                  ->after('probation_confirmed_at')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['probation_end_date', 'probation_status', 'probation_confirmed_at', 'probation_confirmed_by']);
        });
        Schema::dropIfExists('bsc_entries');
        Schema::dropIfExists('bsc_kras');
        Schema::dropIfExists('bsc_cycles');
        Schema::dropIfExists('am_visit_sessions');
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['clock_out_lat', 'clock_out_lng', 'distance_metres']);
        });
    }
};
