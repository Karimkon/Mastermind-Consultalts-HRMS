<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingChange;
use App\Services\ChangeRequestService;
use Illuminate\Http\Request;

/**
 * HR's queue of changes account managers have asked for, on the phone.
 *
 * Nothing in this queue has happened yet. Each row is a proposal against a live
 * record, and the interesting part is the field-by-field diff — what it is now
 * against what it would become, plus whether somebody else has moved it since
 * the request was made.
 *
 * Mirrors Admin\ChangeApprovalController so the two clients cannot drift: same
 * role gate, same service for applying, same refusal to reject without a reason.
 */
class ChangeApprovalApiController extends Controller
{
    private function authorise(): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin']),
            403,
            'Only HR or an administrator can approve changes.'
        );
    }

    public function index(Request $request)
    {
        $this->authorise();

        $status = in_array($request->query('status'), ['approved', 'rejected'], true)
            ? $request->query('status')
            : PendingChange::PENDING;

        $changes = PendingChange::with(['requester', 'reviewer', 'client'])
            ->where('status', $status)
            ->when($request->query('client_id'), fn($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'          => $changes->through(fn($c) => $this->summary($c)),
            'status'        => $status,
            'pending_count' => PendingChange::pending()->count(),
        ]);
    }

    public function show(PendingChange $change)
    {
        $this->authorise();

        $change->load(['requester', 'reviewer', 'client']);
        $subject = $change->subject();

        return response()->json([
            'data' => $this->summary($change) + [
                // The whole point of the screen: what would actually change.
                'diff'         => $change->diff(),
                'has_drift'    => $change->hasDrift(),
                'subject_gone' => $subject === null,
                'review_note'  => $change->review_note,
            ],
        ]);
    }

    public function approve(Request $request, PendingChange $change)
    {
        $this->authorise();

        $request->validate(['review_note' => 'nullable|string|max:1000']);

        $applied = app(ChangeRequestService::class)->approve($change, $request->review_note);

        return response()->json([
            'applied'       => $applied,
            'message'       => $applied
                ? 'Change approved and applied.'
                : 'That change could not be applied — it had already been decided, or the record no longer exists.',
            'pending_count' => PendingChange::pending()->count(),
        ], $applied ? 200 : 409);
    }

    public function reject(Request $request, PendingChange $change)
    {
        $this->authorise();

        // A refusal without a reason leaves the account manager guessing and
        // resubmitting the same thing.
        $request->validate(['review_note' => 'required|string|max:1000'], [
            'review_note.required' => 'Say why, so the account manager knows what to correct.',
        ]);

        $done = app(ChangeRequestService::class)->reject($change, $request->review_note);

        return response()->json([
            'applied'       => $done,
            'message'       => $done ? 'Change rejected.' : 'That change had already been decided.',
            'pending_count' => PendingChange::pending()->count(),
        ], $done ? 200 : 409);
    }

    private function summary(PendingChange $change): array
    {
        return [
            'id'           => $change->id,
            'action'       => $change->action,
            'label'        => $change->label,
            'model_type'   => $change->model_type,
            'model_id'     => $change->model_id,
            'status'       => $change->status,
            'requested_by' => $change->requester?->name,
            'client'       => $change->client?->company_name,
            'reviewed_by'  => $change->reviewer?->name,
            'reviewed_at'  => $change->reviewed_at?->format('Y-m-d H:i'),
            'field_count'  => is_array($change->payload) ? count($change->payload) : 0,
            'created_at'   => $change->created_at?->format('Y-m-d H:i'),
        ];
    }
}
