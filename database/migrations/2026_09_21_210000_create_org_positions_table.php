<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chain of command, as positions rather than people.
 *
 * `employees.manager_id` already records who reports to whom, but a person
 * hierarchy cannot hold a post nobody occupies, and it cannot hold the Board of
 * Directors at all - the Board is not an employee. Both are needed to draw the
 * structure honestly, so positions are kept here and filled by people.
 *
 * `client_id` null means the position belongs to Mastermind's own structure.
 * A client site (Roofings, UNOC, Serena) can carry its own tree, hung beneath
 * the Account Manager who looks after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title');
            // Peers at one level are shown in a deliberate order rather than by
            // id, so "HR and Operations" leads the functional heads.
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('org_positions')->nullOnDelete();
            $table->index(['client_id', 'parent_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('org_position_id')->nullable()->after('designation_id')
                  ->constrained('org_positions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_position_id');
        });
        Schema::dropIfExists('org_positions');
    }
};
