<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('exit_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('exit_date');
            $table->enum('reason', ['resignation','termination','retirement','redundancy','other']);
            $table->text('interview_notes')->nullable();
            $table->boolean('equipment_returned')->default(false);
            $table->text('equipment_notes')->nullable();
            $table->boolean('final_settlement_done')->default(false);
            $table->decimal('settlement_amount', 12, 2)->nullable();
            $table->boolean('clearance_done')->default(false);
            $table->enum('status', ['initiated','in_progress','completed'])->default('initiated');
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('exit_workflows'); }
};
