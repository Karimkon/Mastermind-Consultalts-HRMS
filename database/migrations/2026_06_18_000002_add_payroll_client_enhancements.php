<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Client enhancements ────────────────────────────────────────
        Schema::table('clients', function (Blueprint $table) {
            // Payroll formula settings
            $table->boolean('gross_up_paye')->default(false)->after('geo_fence_radius');
            $table->decimal('gpa_wmc_rate', 5, 2)->default(0)->after('gross_up_paye');   // e.g. 2.00 for 2%
            $table->decimal('billing_rate_multiplier', 5, 3)->default(1.000)->after('gpa_wmc_rate'); // 1.25 = charge 25% more
            $table->string('payroll_type', 20)->default('daily')->after('billing_rate_multiplier');  // daily, hourly, monthly, mixed
        });

        // ── Payroll run enhancements ───────────────────────────────────
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->string('employment_type', 20)->default('all')->after('client_id'); // all, casual, contract
            $table->boolean('hours_based')->default(false)->after('employment_type');   // hourly payroll
            $table->decimal('billing_total', 15, 2)->nullable()->after('hours_based'); // total client invoice
            $table->decimal('billing_rate_override', 15, 2)->nullable()->after('billing_total'); // per-run override
        });

        // ── Employee salary — billing rate ─────────────────────────────
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->decimal('billing_rate', 15, 2)->nullable()->after('basic_salary'); // what client is charged
            $table->string('salary_type')->default('daily')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['gross_up_paye', 'gpa_wmc_rate', 'billing_rate_multiplier', 'payroll_type']);
        });
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['employment_type', 'hours_based', 'billing_total', 'billing_rate_override']);
        });
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn('billing_rate');
        });
    }
};
