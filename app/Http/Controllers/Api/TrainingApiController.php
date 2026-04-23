<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{TrainingCourse, TrainingEnrollment, Certification};
use Illuminate\Http\Request;

class TrainingApiController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingCourse::withCount('enrollments');
        if ($request->search) $query->where('title', 'like', "%{$request->search}%");
        if ($request->status) $query->where('status', $request->status);

        return response()->json([
            'data' => $query->latest()->paginate(12)->through(fn($c) => $this->formatCourse($c)),
        ]);
    }

    public function show(TrainingCourse $training)
    {
        $employee = request()->user()->employee;
        $enrolled = $employee && TrainingEnrollment::where('employee_id', $employee->id)
            ->where('training_course_id', $training->id)->exists();

        return response()->json([
            'data' => $this->formatCourse($training) + [
                'description'  => $training->description,
                'is_enrolled'  => $enrolled,
                'enrolled_count' => $training->enrollments()->count(),
            ],
        ]);
    }

    public function enroll(Request $request, TrainingCourse $training)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $enrollment = TrainingEnrollment::firstOrCreate([
            'employee_id'        => $employee->id,
            'training_course_id' => $training->id,
        ], ['status' => 'enrolled', 'progress' => 0]);

        return response()->json(['data' => ['enrolled' => true, 'id' => $enrollment->id]], 201);
    }

    public function certifications(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => []]);

        return response()->json([
            'data' => Certification::where('employee_id', $employee->id)->latest()->get()->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'issuer'      => $c->issuer,
                'issued_on'   => $c->issued_on?->format('Y-m-d'),
                'expires_on'  => $c->expires_on?->format('Y-m-d'),
            ]),
        ]);
    }

    private function formatCourse(TrainingCourse $c): array
    {
        return [
            'id'             => $c->id,
            'title'          => $c->title,
            'duration_hours' => $c->duration_hours,
            'trainer'        => $c->trainer ?? null,
            'status'         => $c->status ?? 'active',
            'enrollments'    => $c->enrollments_count ?? 0,
        ];
    }
}
