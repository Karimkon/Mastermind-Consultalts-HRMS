<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Where an application came from, and a way for the applicant to follow it.
 *
 * Two things the recruitment brief asked for and nothing recorded:
 *
 *  - which part of the country applications arrive from. Only the IP is
 *    available at the moment somebody applies, so it is kept and the place is
 *    resolved afterwards — an application must never wait on a lookup to a
 *    third party, and must not fail when that lookup does.
 *
 *  - somewhere for the applicant to see what has happened to their
 *    application. A random tracking code is the whole of the authentication:
 *    it is not a login, so it must be unguessable, which is why it is 12
 *    characters of random rather than the candidate id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('source');
            $table->string('country', 80)->nullable()->after('ip_address');
            $table->string('region', 80)->nullable()->after('country');
            $table->string('city', 80)->nullable()->after('region');
            $table->timestamp('origin_resolved_at')->nullable()->after('city');

            $table->string('tracking_code', 16)->nullable()->unique()->after('origin_resolved_at');

            // When the applicant was last told where their application stands,
            // so a status that has not moved does not notify twice.
            $table->string('notified_status', 30)->nullable()->after('tracking_code');
        });

        // Everybody who applied before this still needs a way in.
        DB::table('candidates')->whereNull('tracking_code')->orderBy('id')
            ->each(function ($row) {
                DB::table('candidates')->where('id', $row->id)
                    ->update(['tracking_code' => Str::upper(Str::random(12))]);
            });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn([
                'ip_address', 'country', 'region', 'city',
                'origin_resolved_at', 'tracking_code', 'notified_status',
            ]);
        });
    }
};
