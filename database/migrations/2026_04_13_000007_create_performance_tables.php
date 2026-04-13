<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['financial', 'customer', 'internal_process', 'learning_growth'])->default('financial');
            $table->decimal('weight', 5, 2)->default(0); // percentage weight
            $table->string('unit', 50)->nullable(); // %, $, count, etc.
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->year('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'closed', 'archived'])->default('draft');
            $table->timestamps();
        });

        Schema::create('employee_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('kpi_id');
            $table->unsignedBigInteger('cycle_id');
            $table->decimal('target', 12, 2)->default(0);
            $table->decimal('actual', 12, 2)->nullable();
            $table->decimal('score', 5, 2)->nullable(); // calculated score
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'kpi_id', 'cycle_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('kpi_id')->references('id')->on('kpis')->cascadeOnDelete();
            $table->foreign('cycle_id')->references('id')->on('performance_cycles')->cascadeOnDelete();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->unsignedBigInteger('cycle_id');
            $table->enum('review_type', ['self', 'manager', 'peer', '360'])->default('manager');
            $table->json('ratings')->nullable(); // {category: score}
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('comments')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'submitted', 'approved'])->default('pending');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('cycle_id')->references('id')->on('performance_cycles')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('employee_kpis');
        Schema::dropIfExists('performance_cycles');
        Schema::dropIfExists('kpis');
    }
};
