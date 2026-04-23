<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Questionnaire template per job posting
        Schema::create('shortlisting_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('top_n')->default(10); // pick best N candidates
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Individual questions in a criteria set
        Schema::create('shortlisting_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criteria_id')->constrained('shortlisting_criteria')->cascadeOnDelete();
            $table->text('question');
            // multiple_choice | yes_no | scale | text
            $table->string('question_type')->default('multiple_choice');
            // multiple_choice: [{"text":"...", "is_correct": true/false}, ...]
            // yes_no: null
            // scale / text: null
            $table->json('options')->nullable();
            // yes_no: "yes"/"no" (preferred answer)
            // scale: minimum passing value (1–5)
            // multiple_choice / text: null
            $table->string('correct_answer')->nullable();
            $table->unsignedTinyInteger('weight')->default(5); // 1–10
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One record per candidate per criteria — stores all answers + computed score
        Schema::create('shortlisting_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criteria_id')->constrained('shortlisting_criteria')->cascadeOnDelete();
            // {"question_id": "answer_value", ...}
            $table->json('answers');
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['candidate_id', 'criteria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortlisting_responses');
        Schema::dropIfExists('shortlisting_questions');
        Schema::dropIfExists('shortlisting_criteria');
    }
};
