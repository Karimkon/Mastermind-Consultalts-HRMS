<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityCorrectiveAction;
use App\Models\QualityNonconformity;
use Illuminate\Http\Request;

class CorrectiveActionController extends Controller
{
    public function store(Request $request, QualityNonconformity $nonconformity)
    {
        $data = $request->validate([
            'type' => 'required|in:corrective,preventive',
            'action' => 'required|string',
            'root_cause' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $nonconformity->actions()->create($data + ['status' => 'planned']);

        // Opening work on a non-conformity moves it out of the untouched state.
        if ($nonconformity->status === 'open') {
            $nonconformity->update(['status' => 'investigating']);
        }

        return back()->with('success', 'Action added.');
    }

    public function update(Request $request, QualityCorrectiveAction $action)
    {
        $data = $request->validate([
            'status' => 'required|in:planned,in_progress,completed,verified,cancelled',
            'effectiveness_notes' => 'nullable|string',
        ]);

        if ($data['status'] === 'completed' && ! $action->completed_at) {
            $data['completed_at'] = now();
        }
        if ($data['status'] === 'verified') {
            $data['completed_at'] = $action->completed_at ?: now();
            $data['verified_by'] = $request->user()?->id;
            $data['verified_at'] = now();
        }

        $action->update($data);

        return back()->with('success', 'Action updated.');
    }
}
