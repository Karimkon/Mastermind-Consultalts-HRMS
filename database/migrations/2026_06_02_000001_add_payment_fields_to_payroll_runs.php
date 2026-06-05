<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_runs', 'payment_method'))
                $table->string('payment_method', 50)->nullable()->after('locked_by');
            if (!Schema::hasColumn('payroll_runs', 'payment_reference'))
                $table->string('payment_reference')->nullable()->after('payment_method');
            if (!Schema::hasColumn('payroll_runs', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('payment_reference');
                $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_runs', 'paid_at'))
                $table->timestamp('paid_at')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['payment_method', 'payment_reference', 'paid_by', 'paid_at']);
        });
    }
};
