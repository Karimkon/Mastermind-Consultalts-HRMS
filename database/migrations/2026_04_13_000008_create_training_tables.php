<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code', 30)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('provider', 200)->nullable();
            $table->decimal('duration_hours', 6, 2)->default(0);
            $table->string('material_path')->nullable();
            $table->string('external_url')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('course_id');
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'failed', 'cancelled'])->default('enrolled');
            $table->integer('progress_pct')->default(0);
            $table->date('enrolled_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'course_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('training_courses')->cascadeOnDelete();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('name');
            $table->string('issued_by');
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_courses');
    }
};
