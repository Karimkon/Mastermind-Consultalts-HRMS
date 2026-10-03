<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Management - Phase 2.
 *
 * Score snapshots give the Reports screen its trend line (one row per scope
 * per day), and quality reviews capture the periodic management review that
 * sits on top of the operational checks and audits.
 */
return new class extends Migration {
    public function up(): void
    {
        // A dated point on the quality trend, written by every engine run.
        Schema::create('quality_score_snapshots', function (Blueprint $t) {
            $t->id();
            $t->date('snapshot_date');
            $t->string('scope')->default('overall'); // overall, function, department
            $t->string('scope_key')->nullable();      // the function key or department id
            $t->string('label')->nullable();
            $t->decimal('score', 5, 2)->default(0);
            $t->unsignedInteger('open_nc')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->foreignId('quality_check_run_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            // One snapshot per scope per day - re-running a scan updates it.
            $t->unique(['snapshot_date', 'scope', 'scope_key']);
        });

        // The periodic management review of quality across the HRMS.
        Schema::create('quality_reviews', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();        // QR-0007
            $t->string('title');
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->string('status')->default('draft');   // draft, completed
            $t->foreignId('chaired_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('overall_score', 5, 2)->nullable();
            $t->text('summary')->nullable();
            $t->text('decisions')->nullable();
            $t->date('held_on')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_reviews');
        Schema::dropIfExists('quality_score_snapshots');
    }
};
