<?php
namespace App\Http\Controllers;

use App\Models\{AmVisitSession, Client};
use Illuminate\Http\Request;

class AmVisitController extends Controller
{
    // =========================================================
    // Helper — returns the collection of clients managed by the
    // currently authenticated Account Manager.
    // =========================================================
    private function amClients()
    {
        return Client::where('account_manager_id', auth()->id())
                     ->where('status', 'active')
                     ->orderBy('company_name')
                     ->get();
    }

    // =========================================================
    // INDEX — visit history page
    // =========================================================
    public function index()
    {
        $user = auth()->user();

        $sessions = AmVisitSession::with('client')
            ->where('user_id', $user->id)
            ->orderByDesc('clocked_in_at')
            ->paginate(25);

        $activeSessions = AmVisitSession::with('client')
            ->where('user_id', $user->id)
            ->whereNull('clocked_out_at')
            ->whereDate('clocked_in_at', today())
            ->get();

        $clients = $this->amClients();

        return view('am-visits.index', compact('sessions', 'activeSessions', 'clients'));
    }

    // =========================================================
    // CLOCK IN
    // =========================================================
    public function clockIn(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'lat'       => 'nullable|numeric',
            'lng'       => 'nullable|numeric',
            'notes'     => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        // Ensure the client belongs to this AM
        $client = Client::where('id', $request->client_id)
                        ->where('account_manager_id', $user->id)
                        ->first();

        if (!$client) {
            $msg = 'Client not found or not assigned to you.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 403)
                : back()->with('error', $msg);
        }

        // Block duplicate active sessions for the same client today
        $existing = AmVisitSession::where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->whereNull('clocked_out_at')
            ->whereDate('clocked_in_at', today())
            ->first();

        if ($existing) {
            $msg = 'You already have an active session for ' . $client->company_name . '. Please clock out first.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $siteAddress = $client->work_site_address
            ?: ($client->work_site_lat && $client->work_site_lng
                ? "GPS: {$client->work_site_lat}, {$client->work_site_lng}"
                : null);

        $session = AmVisitSession::create([
            'user_id'       => $user->id,
            'client_id'     => $client->id,
            'lat_in'        => $request->lat,
            'lng_in'        => $request->lng,
            'clocked_in_at' => now(),
            'site_address'  => $siteAddress,
            'notes'         => $request->notes,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'session_id'    => $session->id,
                'client_name'   => $client->company_name,
                'clocked_in_at' => $session->clocked_in_at->format('H:i'),
                'message'       => 'Clocked in at ' . $client->company_name . '.',
            ]);
        }

        return back()->with('success', 'Clocked in at ' . $client->company_name . ' (' . now()->format('H:i') . ').');
    }

    // =========================================================
    // CLOCK OUT
    // =========================================================
    public function clockOut(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'notes'      => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $session = AmVisitSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->whereNull('clocked_out_at')
            ->first();

        if (!$session) {
            $msg = 'Active session not found.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 404)
                : back()->with('error', $msg);
        }

        $session->update([
            'clocked_out_at' => now(),
            'lat_out'        => $request->lat,
            'lng_out'        => $request->lng,
            'notes'          => $request->notes ?? $session->notes,
        ]);

        $hours       = $session->fresh()->duration_hours;
        $clientName  = $session->client->company_name ?? 'client';

        if ($request->wantsJson()) {
            return response()->json([
                'message'        => 'Clocked out from ' . $clientName . '.',
                'clocked_out_at' => now()->format('H:i'),
                'duration_hours' => $hours,
            ]);
        }

        return back()->with('success', 'Clocked out from ' . $clientName . ' at ' . now()->format('H:i') . '. Duration: ' . $hours . 'h.');
    }

    // =========================================================
    // ACTIVE SESSIONS — JSON endpoint
    // =========================================================
    public function activeSessions()
    {
        $sessions = AmVisitSession::with('client')
            ->where('user_id', auth()->id())
            ->whereNull('clocked_out_at')
            ->whereDate('clocked_in_at', today())
            ->get()
            ->map(fn($s) => [
                'session_id'    => $s->id,
                'client_name'   => $s->client?->company_name,
                'clocked_in_at' => $s->clocked_in_at?->format('H:i'),
                'site_address'  => $s->site_address,
            ]);

        return response()->json($sessions);
    }
}
