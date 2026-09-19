<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A client can be more than one place.
 *
 * `clients.work_site_lat` / `work_site_lng` hold exactly one point, and the
 * geo-fence measures against it. That is fine for a hotel. It cannot describe the
 * two clients that actually matter:
 *
 *     Roofings Uganda Limited   439 staff   Lubowa (259), Industrial Area (180)
 *     Mastermind Consult Ltd HQ  29 staff   Head Office (15), Field Staff (14)
 *
 * Roofings' two premises are about ten kilometres apart. Whichever single point
 * were set, the other 180 or 259 people would be permanently outside the fence —
 * so the largest client in the system is the one the current shape cannot express,
 * and setting a coordinate for it would start refusing real staff at the right
 * gate.
 *
 * A client therefore gets many sites, each with its own radius, and a clock-in is
 * checked against the nearest one.
 *
 * The old columns are deliberately left alone. Exports, the admin client form, the
 * account-manager settings page and AmVisitController all read them, and the
 * geo-fence falls back to them when a client has no rows here — so nothing that
 * works today stops working, and a client is migrated simply by being given a
 * site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // "Lubowa", "Industrial Area" — what people call the place, so the
            // attendance record can say which site somebody clocked in at.
            $table->string('name');
            $table->string('address')->nullable();

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            // Per site, because a factory compound and a head office are not the
            // same size.
            $table->unsignedSmallInteger('geo_fence_radius')->default(100);

            // Retired rather than deleted: a site that has been clocked into is
            // part of the history of somebody's attendance.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->index(['client_id', 'is_active']);
        });

        // Carry over whatever single point each client already had, so a client
        // that was working keeps working and the fallback is never needed for it.
        $existing = DB::table('clients')
            ->whereNotNull('work_site_lat')
            ->whereNotNull('work_site_lng')
            ->get();

        foreach ($existing as $client) {
            DB::table('client_sites')->insert([
                'client_id' => $client->id,
                'name' => 'Main site',
                'address' => $client->work_site_address,
                'lat' => $client->work_site_lat,
                'lng' => $client->work_site_lng,
                'geo_fence_radius' => $client->geo_fence_radius ?? 100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sites');
    }
};
