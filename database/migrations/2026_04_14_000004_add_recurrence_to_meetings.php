<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('recurrence', 20)->nullable()->after('minutes');
            $table->date('recurrence_end_date')->nullable()->after('recurrence');
            $table->unsignedBigInteger('parent_meeting_id')->nullable()->after('recurrence_end_date');
            $table->foreign('parent_meeting_id')->references('id')->on('meetings')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['parent_meeting_id']);
            $table->dropColumn(['recurrence','recurrence_end_date','parent_meeting_id']);
        });
    }
};
