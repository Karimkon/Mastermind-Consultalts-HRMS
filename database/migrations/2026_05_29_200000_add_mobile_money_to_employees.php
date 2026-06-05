<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('mobile_money_number', 20)->nullable()->after('bank_branch');
        });

        // Update payment_mode default values (existing 'mobile_money' → 'mtn')
        DB::statement("UPDATE employees SET payment_mode='mtn' WHERE payment_mode='mobile_money'");
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('mobile_money_number');
        });
    }
};
