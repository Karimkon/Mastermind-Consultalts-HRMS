<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingTask;
use App\Models\Pip;
use Illuminate\Http\Request;

/**
 * The two lists that follow a person into and through the job.
 *
 * Onboarding tasks and performance improvement plans are separate modules on the
 * web and are together here because on a phone they are the same shape: a short
 * list belonging to one employee, with items somebody ticks off. Splitting them
 * into two controllers would double the plumbing to say the same thing twice.
 *
 * Both are read-mostly on a handset. Creating a PIP is a conversation with HR and
 * a document, not something to start on a phone; what the phone is genuinely good
 * for is checking where one stands and marking a task done on the day it is done.
 */
class DevelopmentApiController extends Controller
{
    // ── Onboarding ───────────────────────────────────────────────────────

    /**
     * Onboarding tasks, defaulting to the signed-in person's own.
     *
     * HR can ask for anybody's; everybody else gets their own regardless of what
     * they ask for, which is the safer default when the parameter is missing.
     */
    public function onboarding(Request $request)
    {
        $employeeId = $this->resolveEmployeeId($request);

        if (! $employeeId) {
            return response()->json(['data' => []]);
        }

        $tasks = OnboardingTask::with('completedBy')
            ->where('employee_id', $employeeId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $tasks->map(fn (OnboardingTask $t) => [
                'id' => $t->id,
                'task' => $t->task,
                'description' => $t->description,
                'completed' => $t->completed_at !== null,
                'completed_at' => $t->completed_at?->toDateTimeString(),
                'completed_by' => $t->completedBy?->name,
            ])->values(),
            'summary' => [
                'done' => $tasks->whereNotNull('completed_at')->count(),
                'total' => $tasks->count(),
            ],
        ]);
    }

    /**
     * Tick a task, or untick it.
     *
     * Unticking is allowed on purpose. A task marked done by mistake is common,
     * and the alternative — going to a desk to undo a tap — is how people learn
     * not to use the app at all.
     */
    public function completeOnboardingTask(Request $request, OnboardingTask $task)
    {
        $employeeId = $request->user()->employee?->id;

        $mayEdit = $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager'])
            || ($employeeId !== null && $task->employee_id === $employeeId);

        abort_unless($mayEdit, 403, 'This onboarding list is not yours to change.');

        $done = $request->boolean('completed', true);

        $task->update([
            'completed_at' => $done ? now() : null,
            'completed_by' => $done ? $request->user()->id : null,
        ]);

        return response()->json([
            'data' => ['id' => $task->id, 'completed' => $done],
        ]);
    }

    // ── Performance improvement plans ────────────────────────────────────

    public function pips(Request $request)
    {
        $employeeId = $this->resolveEmployeeId($request);

        $query = Pip::with(['employee', 'cycle'])->latest();

        // Somebody without an HR role sees only their own, whatever they ask for.
        if (! $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'md', 'manager'])) {
            if (! $employeeId) {
                return response()->json(['data' => []]);
            }

            $query->where('employee_id', $employeeId);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (Pip $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'employee_name' => $p->employee?->full_name,
                'description' => $p->description,
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                // Stored as JSON. Normalised to a list of strings so the phone
                // does not have to guess whether it got objects or text.
                'objectives' => collect($p->objectives ?? [])
                    ->map(fn ($o) => is_array($o) ? ($o['objective'] ?? $o['title'] ?? json_encode($o)) : (string) $o)
                    ->values(),
                'status' => $p->status,
                'outcome' => $p->outcome,
                'days_remaining' => $p->end_date?->isFuture() === true
                    ? now()->startOfDay()->diffInDays($p->end_date, false)
                    : null,
            ])->values(),
        ]);
    }

    /**
     * The employee this request is about.
     *
     * An explicit employee_id is honoured only for people who are allowed to look
     * at somebody else's record; for everybody else it falls back to their own,
     * rather than refusing, because the common case is the app asking about the
     * signed-in person.
     */
    private function resolveEmployeeId(Request $request): ?int
    {
        $own = $request->user()->employee?->id;

        if ($request->filled('employee_id')
            && $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'md', 'manager'])) {
            return (int) $request->query('employee_id');
        }

        return $own;
    }
}
