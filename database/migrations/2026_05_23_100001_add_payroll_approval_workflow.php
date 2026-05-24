<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            // HR approval
            $table->unsignedBigInteger('hr_approved_by')->nullable()->after('approved_by');
            $table->timestamp('hr_approved_at')->nullable()->after('hr_approved_by');
            // Finance approval
            $table->unsignedBigInteger('finance_approved_by')->nullable()->after('hr_approved_at');
            $table->timestamp('finance_approved_at')->nullable()->after('finance_approved_by');
            // MD approval
            $table->unsignedBigInteger('md_approved_by')->nullable()->after('finance_approved_at');
            $table->timestamp('md_approved_at')->nullable()->after('md_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['hr_approved_by','hr_approved_at','finance_approved_by','finance_approved_at','md_approved_by','md_approved_at']);
        });
    }
};
