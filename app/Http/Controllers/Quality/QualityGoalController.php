<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\QualityDocument;
use App\Models\QualityGoal;
use App\Models\QualityGoalFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QualityGoalController extends Controller
{
    private function canManage(?User $u): bool
    {
        return $u && $u->hasAnyRole(['super-admin', 'hr-admin', 'quality-manager']);
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $manage = $this->canManage($u);

        $goals = QualityGoal::with(['initiator', 'assignee', 'files'])
            ->when(! $manage, fn ($q) => $q->where(function ($w) use ($u) {
                $w->where('assignee_id', $u->id)->orWhere('initiator_id', $u->id);
            }))
            ->latest()->paginate(20);

        $users = $manage
            ? User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('quality.goals.index', compact('goals', 'users', 'manage'));
    }

    public function store(Request $request)
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'files.*' => 'nullable|file|max:' . QualityDocument::maxUploadKb(),
        ]);

        $goal = QualityGoal::create([
            'reference' => QualityGoal::nextReference(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'initiator_id' => $request->user()->id,
            'assignee_id' => $data['assignee_id'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'assigned',
        ]);

        foreach ((array) $request->file('files', []) as $file) {
            $this->storeFile($goal, $file, $request->user()->id);
        }

        // Notify the assignee - this reaches both the in-system bell and the
        // mobile app, which read the same notifications table.
        $this->notify($goal->assignee_id, "New quality goal: {$goal->title}", $goal);

        return redirect()->route('quality.goals.show', $goal)
            ->with('success', "Goal {$goal->reference} assigned.");
    }

    public function show(Request $request, QualityGoal $goal)
    {
        $u = $request->user();
        abort_unless($this->canManage($u) || in_array($u->id, [$goal->assignee_id, $goal->initiator_id], true), 403);

        $goal->load(['initiator', 'assignee', 'files.uploader']);

        return view('quality.goals.show', compact('goal'));
    }

    public function update(Request $request, QualityGoal $goal)
    {
        $u = $request->user();
        abort_unless($this->canManage($u) || $u->id === $goal->assignee_id, 403);

        $data = $request->validate([
            'status' => 'required|in:assigned,in_progress,completed,cancelled',
            'progress_notes' => 'nullable|string',
            'files.*' => 'nullable|file|max:' . QualityDocument::maxUploadKb(),
        ]);

        if ($data['status'] === 'completed' && ! $goal->completed_at) {
            $data['completed_at'] = now();
            if ($goal->initiator_id && $goal->initiator_id !== $u->id) {
                $this->notify($goal->initiator_id, "Goal completed: {$goal->title}", $goal);
            }
        }

        $goal->update($data);

        foreach ((array) $request->file('files', []) as $file) {
            $this->storeFile($goal, $file, $u->id);
        }

        return back()->with('success', 'Goal updated.');
    }

    public function download(Request $request, QualityGoal $goal, QualityGoalFile $file)
    {
        abort_unless($file->quality_goal_id === $goal->id, 404);
        $u = $request->user();
        abort_unless($this->canManage($u) || in_array($u->id, [$goal->assignee_id, $goal->initiator_id], true), 403);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    private function storeFile(QualityGoal $goal, $file, int $userId): void
    {
        $path = $file->store('quality/goals/' . $goal->id, 'local');
        QualityGoalFile::create([
            'quality_goal_id' => $goal->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    private function notify(int $userId, string $title, QualityGoal $goal): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => 'quality_alert',
                'title' => $title,
                'body' => "Quality goal {$goal->reference}" . ($goal->end_date ? " - due {$goal->end_date->format('d M Y')}" : ''),
                'data' => ['url' => route('quality.goals.show', $goal->id), 'reference' => $goal->reference],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
