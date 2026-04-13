<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('training_courses', function (Blueprint $table) {
            $table->unsignedBigInteger('trainer_id')->nullable()->after('is_active');
            $table->string('trainer_name', 200)->nullable()->after('trainer_id');
            $table->foreign('trainer_id')->references('id')->on('employees')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('training_courses', function (Blueprint $table) {
            $table->dropForeign(['trainer_id']);
            $table->dropColumn(['trainer_id','trainer_name']);
        });
    }
};
