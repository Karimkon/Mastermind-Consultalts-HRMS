<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clients: contract payment day + geo-fence fields
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_day')->nullable()->after('notes')
                  ->comment('Day of month salaries are paid per contract (1-31)');
            $table->string('work_site_address')->nullable()->after('payment_day');
            $table->decimal('work_site_lat', 10, 7)->nullable()->after('work_site_address');
            $table->decimal('work_site_lng', 10, 7)->nullable()->after('work_site_lat');
            $table->unsignedSmallInteger('geo_fence_radius')->default(100)->after('work_site_lng')
                  ->comment('Allowed radius metres for employee clock-in');
        });

        // 2. Payroll runs: client_id + lock mechanism
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete()->after('id');
            $table->timestamp('locked_at')->nullable()->after('approved_at');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete()->after('locked_at');
        });

        // 3. Leave requests: replacement person
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('replacement_name')->nullable()->after('reason');
            $table->string('replacement_email')->nullable()->after('replacement_name');
            $table->string('replacement_phone')->nullable()->after('replacement_email');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['payment_day','work_site_address','work_site_lat','work_site_lng','geo_fence_radius']);
        });
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['client_id','locked_at','locked_by']);
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['replacement_name','replacement_email','replacement_phone']);
        });
    }
};
