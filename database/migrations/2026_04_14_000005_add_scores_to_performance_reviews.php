<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->decimal('self_score', 5, 2)->nullable()->after('total_score');
            $table->decimal('peer_score', 5, 2)->nullable()->after('self_score');
            $table->decimal('manager_score', 5, 2)->nullable()->after('peer_score');
        });
    }
    public function down(): void {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn(['self_score','peer_score','manager_score']);
        });
    }
};
