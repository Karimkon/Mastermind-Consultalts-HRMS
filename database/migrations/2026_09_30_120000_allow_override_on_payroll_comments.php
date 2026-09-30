<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The payroll comment trail gains an "override" action.
 *
 * `action` was enum('approved','sent_back','note'). A Super Admin moving a run
 * outside the approval chain is none of those three, and writing it as a plain
 * note would hide the one kind of entry most worth finding later. The trail is
 * the only record the run itself carries of having been moved by hand.
 */
return new class extends Migration
{
    private const WAS = ['approved', 'sent_back', 'note'];
    private const NOW = ['approved', 'sent_back', 'note', 'override'];

    public function up(): void
    {
        Schema::table('payroll_comments', function (Blueprint $table) {
            $table->enum('action', self::NOW)->default('note')->change();
        });
    }

    public function down(): void
    {
        // Anything already recorded as an override would violate the narrower
        // enum, so it becomes a note rather than blocking the rollback.
        DB::table('payroll_comments')->where('action', 'override')->update(['action' => 'note']);

        Schema::table('payroll_comments', function (Blueprint $table) {
            $table->enum('action', self::WAS)->default('note')->change();
        });
    }
};
