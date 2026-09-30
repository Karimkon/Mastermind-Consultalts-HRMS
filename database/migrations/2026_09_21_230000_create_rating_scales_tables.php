<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the appraisal rating scale a thing an administrator owns.
 *
 * It was two hardcoded constants: a 1-5 range written into three Blade loops and
 * two validation rules, and a band table (Poor / Fair / Good / Very Good /
 * Excellent) written into the Appraisal model. A department that rates out of
 * 10, or one that calls 3 "Meets Expectations", could not be served without
 * editing code.
 *
 * `max_points` is the top of the scale and also the divisor behind the overall
 * percentage - an index of 2.76 is 55.2% of 5, and would be 27.6% of 10 - so it
 * is held once here rather than repeated wherever the percentage is worked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedTinyInteger('max_points')->default(5);
            // Exactly one scale is the default; the application enforces that,
            // because "the one used when nobody chose" is a business rule rather
            // than something a unique index can express on its own.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rating_scale_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_scale_id')->constrained()->cascadeOnDelete();
            // The score itself (1, 2, 3 ...). Doubles as the band number, the
            // way the printed card does it: a 3 is both a rating and "Good".
            $table->unsignedTinyInteger('points');
            $table->string('label');
            // Lower bound of the overall percentage that lands in this band.
            $table->decimal('min_percent', 5, 2)->default(0);
            $table->string('range_label')->nullable();   // e.g. "66 - 75%"
            $table->timestamps();

            $table->unique(['rating_scale_id', 'points']);
        });

        Schema::table('appraisals', function (Blueprint $table) {
            $table->foreignId('rating_scale_id')->nullable()->after('appraisal_template_id')
                  ->constrained('rating_scales')->nullOnDelete();
        });

        // A template carries a scale so cards built from it inherit one without
        // anybody having to remember.
        Schema::table('appraisal_templates', function (Blueprint $table) {
            $table->foreignId('rating_scale_id')->nullable()->after('id')
                  ->constrained('rating_scales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rating_scale_id');
        });
        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rating_scale_id');
        });
        Schema::dropIfExists('rating_scale_bands');
        Schema::dropIfExists('rating_scales');
    }
};
