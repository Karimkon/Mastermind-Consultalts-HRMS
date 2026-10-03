<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityNonconformity;
use App\Models\User;
use Illuminate\Http\Request;

class NonconformityController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'open');
        $severity = $request->input('severity');
        $function = $request->input('function');

        $items = QualityNonconformity::with(['check', 'assignee', 'actions'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', QualityNonconformity::OPEN_STATES))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($severity, fn ($q) => $q->where('severity', $severity))
            ->when($function, fn ($q) => $q->where('hr_function', $function))
            ->orderBySeverity()
            ->latest('detected_at')
            ->paginate(25)->withQueryString();

        return view('quality.nonconformities.index', compact('items', 'status', 'severity', 'function'));
    }

    public function show(QualityNonconformity $nonconformity)
    {
        $nonconformity->load(['check.standard', 'actions.owner', 'assignee', 'raiser']);
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('quality.nonconformities.show', compact('nonconformity', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hr_function' => 'required|string|max:50',
            'severity' => 'required|in:low,medium,high,critical',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $nc = QualityNonconformity::create($data + [
            'reference' => QualityNonconformity::nextReference(),
            'source' => 'manual',
            'status' => 'open',
            'raised_by' => $request->user()?->id,
            'detected_at' => now(),
        ]);

        return redirect()->route('quality.nonconformities.show', $nc)
            ->with('success', "Non-conformity {$nc->reference} raised.");
    }

    public function update(Request $request, QualityNonconformity $nonconformity)
    {
        $data = $request->validate([
            'status' => 'required|in:open,investigating,resolved,closed,risk_accepted',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'resolution_notes' => 'nullable|string',
        ]);

        if (in_array($data['status'], ['resolved', 'closed'], true) && ! $nonconformity->resolved_at) {
            $data['resolved_at'] = now();
        }
        if ($data['status'] === 'open') {
            $data['resolved_at'] = null;
        }

        $nonconformity->update($data);

        return back()->with('success', 'Non-conformity updated.');
    }
}
