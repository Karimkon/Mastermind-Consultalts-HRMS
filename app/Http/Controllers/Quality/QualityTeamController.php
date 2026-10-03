<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Appointing the Quality Management team. The CEO (md) or a System Admin
 * (super-admin) puts someone in charge of the module by granting the
 * quality-manager role, or names auditors.
 */
class QualityTeamController extends Controller
{
    private function authorizeAppointer(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'md']), 403,
            'Only the CEO or a System Admin can appoint the quality team.');
    }

    public function index(Request $request)
    {
        $this->authorizeAppointer($request);

        $managers = User::role('quality-manager')->orderBy('name')->get();
        $auditors = User::role('auditor')->orderBy('name')->get();
        $candidates = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
            ->orderBy('name')->get(['id', 'name', 'email']);

        return view('quality.team.index', compact('managers', 'auditors', 'candidates'));
    }

    public function appoint(Request $request)
    {
        $this->authorizeAppointer($request);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:quality-manager,auditor',
        ]);

        $user = User::findOrFail($data['user_id']);
        $user->assignRole($data['role']);

        $label = $data['role'] === 'quality-manager' ? 'Quality Manager' : 'Auditor';
        $this->notify($user->id, "You have been appointed {$label}",
            "You are now responsible for Quality Management. Open the Quality module to begin.");

        return back()->with('success', "{$user->name} appointed as {$label}.");
    }

    public function revoke(Request $request)
    {
        $this->authorizeAppointer($request);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:quality-manager,auditor',
        ]);

        User::findOrFail($data['user_id'])->removeRole($data['role']);

        return back()->with('success', 'Appointment removed.');
    }

    private function notify(int $userId, string $title, string $body): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => 'quality_alert',
                'title' => $title,
                'body' => $body,
                'data' => ['url' => route('quality.dashboard')],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
