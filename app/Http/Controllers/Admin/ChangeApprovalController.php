<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PendingChange;
use App\Services\ChangeRequestService;
use Illuminate\Http\Request;

/**
 * HR's queue of changes account managers have asked for.
 *
 * Nothing in this queue has happened. Each row is a proposal against a live
 * record, shown field by field as what it is now against what it would become.
 */
class ChangeApprovalController extends Controller
{
    private function authorise(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'hr-admin']), 403,
            'Only HR or an administrator can approve changes.');
    }

    public function index(Request $request)
    {
        $this->authorise();

        $status = in_array($request->query('status'), ['approved', 'rejected'], true)
            ? $request->query('status')
            : PendingChange::PENDING;

        $changes = PendingChange::with(['requester', 'reviewer', 'client'])
            ->where('status', $status)
            ->when($request->query('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.change-approvals.index', [
            'changes'      => $changes,
            'status'       => $status,
            'pendingCount' => PendingChange::pending()->count(),
            'clients'      => Client::orderBy('company_name')->get(),
        ]);
    }

    public function show(PendingChange $change)
    {
        $this->authorise();

        $change->load(['requester', 'reviewer', 'client']);

        return view('admin.change-approvals.show', [
            'change'  => $change,
            'diff'    => $change->diff(),
            'subject' => $change->subject(),
        ]);
    }

    public function approve(Request $request, PendingChange $change)
    {
        $this->authorise();

        $request->validate(['review_note' => 'nullable|string|max:1000']);

        $applied = app(ChangeRequestService::class)->approve($change, $request->review_note);

        return redirect()->route('admin.change-approvals.index')->with(
            $applied ? 'success' : 'error',
            $applied
                ? 'Change approved and applied.'
                : 'That change could not be applied — it had already been decided, or the record no longer exists.'
        );
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

        return redirect()->route('admin.change-approvals.index')->with(
            $done ? 'success' : 'error',
            $done ? 'Change rejected.' : 'That change had already been decided.'
        );
    }
}
