<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One premises belonging to a client.
 *
 * A client can have several — Roofings has Lubowa and Industrial Area, about ten
 * kilometres apart — and a clock-in is checked against whichever is nearest.
 */
class ClientSite extends Model
{
    protected $fillable = [
        'client_id', 'name', 'address', 'lat', 'lng', 'geo_fence_radius', 'is_active',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'geo_fence_radius' => 'integer',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Metres from this site to a point, on a sphere.
     *
     * Haversine. Over the distances that matter here — tens of metres to a few
     * kilometres — the error against a proper ellipsoid is far smaller than a
     * phone's own GPS accuracy, so the extra precision would be imaginary.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $radius = 6371000;

        $phi1 = deg2rad((float) $this->lat);
        $phi2 = deg2rad($lat);
        $dPhi = deg2rad($lat - (float) $this->lat);
        $dLambda = deg2rad($lng - (float) $this->lng);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function covers(float $lat, float $lng): bool
    {
        return $this->distanceTo($lat, $lng) <= $this->geo_fence_radius;
    }
}
