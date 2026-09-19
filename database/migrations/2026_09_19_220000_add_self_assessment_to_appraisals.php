<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employee speaks first.
 *
 * The card used to reach the person being appraised only at the end, to read a
 * finished score and sign it. It now goes to them before anybody rates them: they
 * report what they actually achieved against each target and rate themselves, and
 * the appraiser scores with that in front of them.
 *
 *     draft            HR or the manager sets the KRAs and weights
 *     self_assessment  the employee reports what they achieved, and self-rates   <- new
 *     with_appraiser   the appraiser sets the official rating
 *     with_manager     the line manager confirms, or sends it back
 *     with_employee    the employee reads the final scores and signs
 *     completed
 *
 * `self_rating` is a separate column rather than the employee writing into
 * `rating`. Two people rate the same KPI and they will not always agree — that
 * disagreement is the useful part of the conversation, and storing both in one
 * field would silently destroy whichever was written second.
 *
 * `actual_achieved` is shared on purpose. It is a fact about the period, not an
 * opinion: the employee states it, and the appraiser corrects it if it is wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_kpis', function (Blueprint $table) {
            $table->unsignedTinyInteger('self_rating')->nullable()->after('rating');
            $table->text('self_note')->nullable()->after('self_rating');
        });

        Schema::table('appraisals', function (Blueprint $table) {
            // When the employee finished their half, kept apart from
            // employee_signed_at, which still records the final sign-off.
            $table->timestamp('self_assessed_at')->nullable()->after('employee_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_kpis', function (Blueprint $table) {
            $table->dropColumn(['self_rating', 'self_note']);
        });

        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropColumn('self_assessed_at');
        });
    }
};
