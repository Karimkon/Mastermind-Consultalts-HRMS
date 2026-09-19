<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Say, on the record, whether a clock-in's location was actually checked.
 *
 * The geo-fence works and does block. It has never run in production, because
 * twelve of the thirteen clients have no `work_site_lat`/`work_site_lng` and the
 * check opens with:
 *
 *     if (!$client || !$client->work_site_lat || !$client->work_site_lng) return null;
 *
 * Missing coordinates therefore meant "permitted", silently — and because that
 * early return comes before the "location is required" branch, GPS was not even
 * demanded. Every one of the 66 attendance rows looks exactly like a verified
 * on-site clock-in, and none of them is one.
 *
 * The dangerous part was that it looked configured: every client carries
 * `attendance_enabled = 1` and `geo_fence_radius = 100`. The radius was on screen.
 * It simply had no centre to measure from.
 *
 * A control that fails open should at least fail visibly. `location_status`
 * records which of four things actually happened, so a report can distinguish a
 * verified clock-in from one nobody could check:
 *
 *     verified  a fence was configured and the fix was inside it
 *     outside   a fence was configured and the fix was outside it
 *     unfenced  the client has no coordinates, so nothing could be checked
 *     no_fix    the device supplied no location
 *
 * Existing rows are left null rather than back-filled to `unfenced`. They predate
 * the column and nobody knows what was true when they were written; guessing
 * would put a fact into the record that was never established.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('location_status', 20)->nullable()->after('distance_metres');
            $table->index('location_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['location_status']);
            $table->dropColumn('location_status');
        });
    }
};
