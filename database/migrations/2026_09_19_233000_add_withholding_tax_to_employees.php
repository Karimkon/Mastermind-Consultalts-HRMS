<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax, per employee.
 *
 * Uganda withholds 6% on payments for services. It is not PAYE: PAYE is an
 * employee's income tax on a graduated scale, WHT is a flat percentage of the
 * gross payment to someone engaged for services. Somebody on a consultancy
 * arrangement gets one, a salaried employee the other, so the two are separate
 * switches rather than one tax setting.
 *
 * The rate is stored per employee rather than hard-coded at 6%, because the
 * statutory rate has moved before and a payslip must reproduce the rate that
 * was actually applied at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'charge_wht')) {
                $table->boolean('charge_wht')->default(false)->after('charge_paye');
            }

            if (! Schema::hasColumn('employees', 'wht_percentage')) {
                $table->decimal('wht_percentage', 5, 2)->default(6.00)->after('charge_wht');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['charge_wht', 'wht_percentage'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
