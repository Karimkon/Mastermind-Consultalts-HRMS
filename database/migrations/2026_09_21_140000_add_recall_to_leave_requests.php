<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bringing somebody back from leave early, or changing how long they are away.
 *
 * The dates on the request are what was asked for and approved. When leave is
 * cut short or extended afterwards the dates move, so the original span is kept
 * alongside — otherwise a recalled leave reads as though only the shorter
 * period was ever requested, and the balance arithmetic has nothing to check
 * itself against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'recalled_at')) {
                $table->timestamp('recalled_at')->nullable()->after('actioned_at');
                $table->foreignId('recalled_by')->nullable()->after('recalled_at')
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('leave_requests', 'original_to_date')) {
                // Only set the first time the span is changed, so it always
                // holds what was actually approved.
                $table->date('original_to_date')->nullable()->after('to_date');
                $table->decimal('original_days', 6, 2)->nullable()->after('days_count');
            }

            if (! Schema::hasColumn('leave_requests', 'adjustment_note')) {
                $table->text('adjustment_note')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            foreach (['recalled_at', 'original_to_date', 'original_days', 'adjustment_note'] as $column) {
                if (Schema::hasColumn('leave_requests', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('leave_requests', 'recalled_by')) {
                $table->dropForeign(['recalled_by']);
                $table->dropColumn('recalled_by');
            }
        });
    }
};
