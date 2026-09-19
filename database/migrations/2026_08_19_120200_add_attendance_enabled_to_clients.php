<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every client wants their staff clocking in through this system — some
 * run their own register. Turning it off hides clock in/out for that client's
 * employees; their payroll then runs off the manual days sheet, as it does now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('attendance_enabled')->default(true)->after('geo_fence_radius');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('attendance_enabled');
        });
    }
};
