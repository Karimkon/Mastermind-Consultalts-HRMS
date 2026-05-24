<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('contact_person');
            $table->string('email')->nullable()->after('phone');
            $table->string('deployment_area')->nullable()->after('address');
            $table->string('work_area')->nullable()->after('deployment_area');
        });

        // Employee transfer history table
        Schema::create('employee_client_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('client_id');
            $table->string('type')->default('assignment'); // assignment, transfer, removal
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_client_transfers');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phone','email','deployment_area','work_area']);
        });
    }
};
