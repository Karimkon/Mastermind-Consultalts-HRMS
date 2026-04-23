<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('passport_number', 50)->nullable()->after('national_id');
            $table->string('next_of_kin_name', 255)->nullable()->after('emergency_contact_phone');
            $table->string('next_of_kin_relation', 100)->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_phone', 20)->nullable()->after('next_of_kin_relation');
            $table->string('next_of_kin_email', 255)->nullable()->after('next_of_kin_phone');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['passport_number','next_of_kin_name','next_of_kin_relation','next_of_kin_phone','next_of_kin_email']);
        });
    }
};
