<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The assessment is out of a fixed total.
 *
 * The business rule is that every screening questionnaire is worth 30 marks,
 * shared out across its questions by the recruiter. Nothing enforced a total
 * before: weights ran 1-10 across up to fifteen questions, so one job could
 * be scored out of 12 and the next out of 140, and the two percentages were
 * not comparable even though they were shown side by side on the same list.
 *
 * Kept on the criteria rather than in config so a questionnaire that was set
 * under a different total still scores against the total it was built for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shortlisting_criteria', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_marks')->default(30)->after('top_n');
        });
    }

    public function down(): void
    {
        Schema::table('shortlisting_criteria', function (Blueprint $table) {
            $table->dropColumn('total_marks');
        });
    }
};
