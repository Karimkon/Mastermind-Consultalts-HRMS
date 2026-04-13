<?php
namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\{TrainingCourse, TrainingAssessment, TrainingEnrollment};
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function store(Request $request, TrainingCourse $training)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'score'       => 'required|numeric|min:0',
            'max_score'   => 'required|numeric|min:1',
        ]);

        $score    = (float) $request->score;
        $maxScore = (float) $request->max_score;
        $passed   = ($score / $maxScore) >= 0.70;

        $assessment = TrainingAssessment::updateOrCreate(
            ['course_id' => $training->id, 'employee_id' => $request->employee_id],
            [
                'score'       => $score,
                'max_score'   => $maxScore,
                'passed'      => $passed,
                'passed_at'   => $passed ? now() : null,
                'attempts'    => \DB::raw('attempts + 1'),
                'notes'       => $request->notes,
                'assessed_by' => auth()->id(),
            ]
        );

        // Update enrollment status
        if ($passed) {
            TrainingEnrollment::where('course_id', $training->id)
                ->where('employee_id', $request->employee_id)
                ->update(['status' => 'completed', 'completed_at' => now(), 'progress_pct' => 100]);
        }

        return back()->with('success', $passed ? 'Assessment recorded. Employee passed!' : 'Assessment recorded. Employee did not pass (score: ' . round($score/$maxScore*100, 1) . '%).');
    }
}
