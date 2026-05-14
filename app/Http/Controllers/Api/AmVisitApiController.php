<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{AmVisitSession, Client};
use Illuminate\Http\Request;

class AmVisitApiController extends Controller
{
    private function amClients()
    {
        return Client::where('account_manager_id', auth()->id())
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get();
    }

    public function index(Request $request)
    {
        $perPage  = min($request->get('per_page', 20), 100);
        $sessions = AmVisitSession::with('client')
            ->where('user_id', auth()->id())
            ->orderByDesc('clocked_in_at')
            ->paginate($perPage);

        $data = $sessions->map(fn($s) => $this->formatSession($s));

        return response()->json([
            'data'         => $data,
            'total'        => $sessions->total(),
            'current_page' => $sessions->currentPage(),
            'last_page'    => $sessions->lastPage(),
        ]);
    }

    public function clockIn(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'lat'       => 'required|numeric',
            'lng'       => 'required|numeric',
            'notes'     => 'nullable|string|max:500',
        ]);

        $user   = auth()->user();
        $client = Client::where('id', $request->client_id)
            ->where('account_manager_id', $user->id)
            ->first();

        if (!$client) {
            return response()->json(['message' => 'Client not found or not assigned to you.'], 403);
        }

        $existing = AmVisitSession::where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->whereNull('clocked_out_at')
            ->whereDate('clocked_in_at', today())
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have an active session for ' . $client->company_name . '. Please clock out first.',
            ], 422);
        }

        // Soft geo-fence check — warn but do not block (AMs are mobile workers)
        $geoWarning    = null;
        $distanceMetres = null;
        if ($client->work_site_lat && $client->work_site_lng) {
            $distanceMetres = round($this->distanceMetres(
                (float) $request->lat, (float) $request->lng,
                (float) $client->work_site_lat, (float) $client->work_site_lng
            ), 1);
            $radius = (int) ($client->geo_fence_radius ?? 100);
            if ($distanceMetres > $radius) {
                $geoWarning = "You are {$distanceMetres}m from {$client->company_name} (expected within {$radius}m).";
            }
        }

        $session = AmVisitSession::create([
            'user_id'       => $user->id,
            'client_id'     => $client->id,
            'lat_in'        => $request->lat,
            'lng_in'        => $request->lng,
            'clocked_in_at' => now(),
            'site_address'  => $client->work_site_address,
            'notes'         => $request->notes,
        ]);

        return response()->json([
            'message'         => 'Clocked in at ' . $client->company_name . '.',
            'session'         => $this->formatSession($session->load('client')),
            'distance_metres' => $distanceMetres,
            'geo_warning'     => $geoWarning,
        ], 201);
    }

    public function clockOut(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'notes'      => 'nullable|string|max:500',
        ]);

        $session = AmVisitSession::where('id', $request->session_id)
            ->where('user_id', auth()->id())
            ->whereNull('clocked_out_at')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Active session not found.'], 404);
        }

        $session->update([
            'clocked_out_at' => now(),
            'lat_out'        => $request->lat,
            'lng_out'        => $request->lng,
            'notes'          => $request->notes ?? $session->notes,
        ]);

        return response()->json([
            'message' => 'Clocked out from ' . ($session->client?->company_name ?? 'client') . '.',
            'session' => $this->formatSession($session->fresh()->load('client')),
        ]);
    }

    public function activeSessions()
    {
        $sessions = AmVisitSession::with('client')
            ->where('user_id', auth()->id())
            ->whereNull('clocked_out_at')
            ->whereDate('clocked_in_at', today())
            ->get()
            ->map(fn($s) => $this->formatSession($s));

        return response()->json($sessions);
    }

    public function clients()
    {
        $clients = $this->amClients()->map(fn($c) => [
            'id'              => $c->id,
            'company_name'    => $c->company_name,
            'work_site_address' => $c->work_site_address,
            'work_site_lat'   => $c->work_site_lat,
            'work_site_lng'   => $c->work_site_lng,
            'geo_fence_radius'=> $c->geo_fence_radius,
        ]);

        return response()->json($clients);
    }

    private function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lng2 - $lng1);
        $a    = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    private function formatSession(AmVisitSession $s): array
    {
        $durationHours = null;
        if ($s->clocked_out_at && $s->clocked_in_at) {
            $durationHours = round($s->clocked_in_at->diffInMinutes($s->clocked_out_at) / 60, 2);
        }

        return [
            'id'              => $s->id,
            'client_id'       => $s->client_id,
            'client_name'     => $s->client?->company_name,
            'site_address'    => $s->site_address,
            'clocked_in_at'   => $s->clocked_in_at?->toIso8601String(),
            'clocked_out_at'  => $s->clocked_out_at?->toIso8601String(),
            'lat_in'          => $s->lat_in,
            'lng_in'          => $s->lng_in,
            'lat_out'         => $s->lat_out,
            'lng_out'         => $s->lng_out,
            'notes'           => $s->notes,
            'duration_hours'  => $durationHours,
            'is_active'       => is_null($s->clocked_out_at),
        ];
    }
}
