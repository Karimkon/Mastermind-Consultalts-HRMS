<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('training_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->decimal('max_score', 5, 2)->default(100);
            $table->boolean('passed')->default(false);
            $table->timestamp('passed_at')->nullable();
            $table->tinyInteger('attempts')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('training_assessments'); }
};
