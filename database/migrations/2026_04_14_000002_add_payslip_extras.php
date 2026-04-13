<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('overtime_pay', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('leave_deduction', 12, 2)->default(0)->after('overtime_pay');
            $table->decimal('prorate_factor', 5, 4)->default(1.0000)->after('leave_deduction');
            $table->integer('overtime_hours_paid')->default(0)->after('prorate_factor');
        });
    }
    public function down(): void {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['overtime_pay','leave_deduction','prorate_factor','overtime_hours_paid']);
        });
    }
};
