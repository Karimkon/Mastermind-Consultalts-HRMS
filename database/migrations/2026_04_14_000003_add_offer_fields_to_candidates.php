<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('offer_amount', 12, 2)->nullable()->after('notes');
            $table->date('offer_date')->nullable()->after('offer_amount');
            $table->date('offer_expiry')->nullable()->after('offer_date');
            $table->string('offer_letter_path')->nullable()->after('offer_expiry');
            $table->integer('experience_years')->nullable()->after('offer_letter_path');
            $table->string('education_level', 100)->nullable()->after('experience_years');
            $table->json('score_breakdown')->nullable()->after('education_level');
        });
    }
    public function down(): void {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['offer_amount','offer_date','offer_expiry','offer_letter_path','experience_years','education_level','score_breakdown']);
        });
    }
};
