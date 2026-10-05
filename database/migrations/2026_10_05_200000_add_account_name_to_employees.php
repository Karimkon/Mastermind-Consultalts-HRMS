<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name the account is held in.
 *
 * Not the same as the employee's name often enough to matter: on the Roofings
 * September schedule 14 of 74 people are paid into an account belonging to
 * somebody else — a spouse, a relative, whoever has the registered line. The
 * bank and mobile-money schedules both carry an "Account names" column for
 * exactly this, and the system had nowhere to put it, so it was being retyped
 * into a spreadsheet every month.
 *
 * It serves whichever payment mode the employee is on: the account holder for
 * a bank transfer, the registered name for mobile money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('account_name')->nullable()->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('account_name');
        });
    }
};
