<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When job seekers were told about this posting.
 *
 * Without it, every save of an already-published posting would send the whole
 * matching audience another "new vacancy" alert. Existing postings are
 * stamped as already announced, so turning this on does not blast everybody
 * about jobs that have been open for weeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->timestamp('seekers_notified_at')->nullable()->after('is_public');
        });

        DB::table('job_postings')->update(['seekers_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('seekers_notified_at');
        });
    }
};
