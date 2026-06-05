<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // salary_type on employee_salaries: monthly (default), daily, hourly
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->enum('salary_type', ['monthly', 'daily', 'hourly'])->default('monthly')->after('basic_salary');
        });

        // charge_paye on employees: deduct PAYE by default; admin can uncheck to exempt
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('charge_paye')->default(true)->after('tax_paid_by_employer');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn('salary_type');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('charge_paye');
        });
    }
};
