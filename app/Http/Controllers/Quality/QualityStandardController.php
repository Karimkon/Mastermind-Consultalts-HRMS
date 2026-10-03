<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityStandard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QualityStandardController extends Controller
{
    private const FUNCTIONS = ['employee_central', 'payroll', 'recruitment', 'attendance', 'leave', 'performance', 'compliance'];

    public function index()
    {
        $standards = QualityStandard::withCount('checks')
            ->orderBy('hr_function')->orderByDesc('weight')->get()
            ->groupBy('hr_function');

        return view('quality.standards.index', compact('standards'));
    }

    public function store(Request $request)
    {
        $data = $this->validateStandard($request);
        $data['code'] = $data['code'] ?: $this->nextCode($data['hr_function']);
        $data['created_by'] = $request->user()?->id;
        $data['is_active'] = true;

        QualityStandard::create($data);

        return back()->with('success', 'Standard added.');
    }

    public function update(Request $request, QualityStandard $standard)
    {
        $standard->update($this->validateStandard($request, $standard->id));

        return back()->with('success', "Standard {$standard->code} updated.");
    }

    public function toggle(QualityStandard $standard)
    {
        $standard->update(['is_active' => ! $standard->is_active]);

        return back()->with('success', "Standard {$standard->code} " . ($standard->is_active ? 'activated' : 'deactivated') . '.');
    }

    private function validateStandard(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('quality_standards', 'code')->ignore($ignoreId)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hr_function' => ['required', Rule::in(self::FUNCTIONS)],
            'category' => 'required|in:data_quality,compliance,process,accuracy',
            'severity' => 'required|in:low,medium,high,critical',
            'weight' => 'required|integer|min:1|max:100',
            'target_score' => 'required|integer|min:1|max:100',
        ]);
    }

    private function nextCode(string $function): string
    {
        $abbr = strtoupper(substr(preg_replace('/[^a-z]/i', '', $function), 0, 3));
        $n = QualityStandard::where('hr_function', $function)->count() + 1;
        return 'QS-' . $abbr . '-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
