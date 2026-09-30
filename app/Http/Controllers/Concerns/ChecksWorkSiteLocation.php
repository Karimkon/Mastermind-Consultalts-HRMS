<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AttendanceLog;
use App\Models\Client;
use App\Models\Employee;

/**
 * Where somebody was when they clocked in, and what that is worth as evidence.
 *
 * Shared between the web and the API because the two had drifted: the web
 * measured against the legacy `clients.work_site_lat/lng`, the API against the
 * `client_sites` table, so a client with two premises behaved differently
 * depending on which one the person had in their hand.
 *
 * The fence records, it does not refuse. Both clients used to reject a clock-in
 * taken outside the radius, and `attendance_logs` feeds payroll — so a pin
 * dropped a couple of hundred metres off, or a phone with a poor fix under a
 * factory roof, cost somebody a day's pay and left them no way to register that
 * they had turned up. Flagging it puts the same fact in front of HR with the
 * distance attached, and lets them decide. Refusing loses the day entirely.
 */
trait ChecksWorkSiteLocation
{
    protected function employeeClient(Employee $employee): ?Client
    {
        return Client::whereHas('employees', fn($q) => $q->where('employees.id', $employee->id))->first();
    }

    /**
     * What could actually be established about this fix.
     *
     * The distance is deliberately null rather than a number when there is
     * nothing to measure against. An earlier version computed it as
     *
     *     $client ? $this->distanceMetres($lat, $lng, (float) $client->work_site_lat, ...)
     *
     * which casts a null work_site_lat to 0.0 — so for an unmapped client it
     * measured the distance to Null Island, three thousand kilometres off the
     * coast of Ghana, and stored that as though it meant something.
     *
     * @return array{0: float|null, 1: string, 2: \App\Models\ClientSite|null}
     *         distance in metres (null when nothing could be measured), one of
     *         AttendanceLog's LOCATION_* constants, and the site measured against.
     */
    protected function assessLocation(?Client $client, $lat, $lng): array
    {
        $hasFix = $lat !== null && $lng !== null && $lat !== '' && $lng !== '';

        if (! $hasFix) {
            return [null, AttendanceLog::LOCATION_NO_FIX, null];
        }

        // Nearest of however many premises this client has, so Industrial Area
        // staff are not measured against Lubowa.
        $nearest = $client?->nearestSite((float) $lat, (float) $lng);

        if ($nearest === null) {
            // A fix was given and there is nothing to measure it against. The
            // coordinates are still stored; the status says they prove nothing.
            return [null, AttendanceLog::LOCATION_UNFENCED, null];
        }

        [$site, $distance] = $nearest;

        return [
            round($distance, 2),
            $distance <= $site->geo_fence_radius ? AttendanceLog::LOCATION_VERIFIED : AttendanceLog::LOCATION_OUTSIDE,
            $site,
        ];
    }

    /**
     * What to tell the person, when there is anything worth telling them.
     *
     * Names the site rather than only the client: "9km from Lubowa" is
     * actionable where "9km from Roofings Uganda Limited" baffles somebody
     * standing at Industrial Area.
     */
    protected function locationNotice(?Client $client, ?float $distance, string $status, $site): ?string
    {
        return match ($status) {
            AttendanceLog::LOCATION_OUTSIDE => sprintf(
                'Recorded, but you were about %dm from %s%s — the limit is %dm, so this has been flagged for HR.',
                round((float) $distance),
                $site?->name ?? 'the work site',
                $client ? ' (' . $client->company_name . ')' : '',
                $site?->geo_fence_radius ?? 0,
            ),
            AttendanceLog::LOCATION_NO_FIX => 'Recorded, but your device gave no location, so it could not be verified.',
            default => null,
        };
    }
}
