<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('reference_number', 50)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            $table->string('location', 100)->nullable();
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->integer('vacancies')->default(1);
            $table->date('deadline')->nullable();
            $table->enum('status', ['draft', 'open', 'closed', 'filled'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_posting_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('resume_path')->nullable();
            $table->text('cover_letter')->nullable();
            $table->integer('score')->default(0); // AI / keyword matching score
            $table->json('skills_matched')->nullable();
            $table->enum('status', ['new', 'screening', 'shortlisted', 'interview', 'offer', 'hired', 'rejected'])->default('new');
            $table->text('notes')->nullable();
            $table->string('source', 100)->nullable(); // LinkedIn, Website, Referral
            $table->timestamps();

            $table->foreign('job_posting_id')->references('id')->on('job_postings')->cascadeOnDelete();
            $table->index(['job_posting_id', 'status']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('interviewer_id')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('location', 200)->nullable();
            $table->enum('type', ['phone', 'video', 'in_person', 'technical', 'hr'])->default('in_person');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->integer('rating')->nullable(); // 1-5
            $table->text('feedback')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_postings');
    }
};
