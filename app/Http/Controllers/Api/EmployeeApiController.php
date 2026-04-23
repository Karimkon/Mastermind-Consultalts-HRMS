<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Employee, Department, Designation};
use Illuminate\Http\Request;

class EmployeeApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['user', 'department', 'designation']);

        if ($request->search) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('first_name', 'like', "%$s%")
                  ->orWhere('last_name', 'like', "%$s%")
                  ->orWhere('emp_number', 'like', "%$s%")
            );
        }
        if ($request->department_id) $query->where('department_id', $request->department_id);
        if ($request->status)        $query->where('status', $request->status);

        return response()->json([
            'data' => $query->orderBy('first_name')->paginate(20)->through(fn($e) => $this->format($e)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'emp_number'   => 'required|string|unique:employees',
            'department_id'=> 'nullable|exists:departments,id',
            'hire_date'    => 'required|date',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
        ]);

        $employee = Employee::create($request->only([
            'first_name','last_name','emp_number','department_id','designation_id',
            'manager_id','phone','personal_email','date_of_birth','gender',
            'national_id','address','city','country','hire_date','employment_type',
            'status','bio',
        ]) + ['status' => $request->status ?? 'active']);

        return response()->json(['data' => $this->format($employee->load('user','department','designation'))], 201);
    }

    public function show(Employee $employee)
    {
        $employee->load(['user','department','designation','manager.user']);
        return response()->json(['data' => $this->format($employee, true)]);
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
        ]);
        $employee->update($request->only([
            'first_name','last_name','department_id','designation_id','manager_id',
            'phone','personal_email','address','city','country','status','bio',
            'employment_type','gender',
        ]));
        return response()->json(['data' => $this->format($employee->fresh()->load('user','department','designation'))]);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function departments()
    {
        return response()->json([
            'data' => Department::where('is_active', true)->orderBy('name')->get()
                ->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'code' => $d->code]),
        ]);
    }

    public function designations()
    {
        return response()->json([
            'data' => Designation::orderBy('title')->get()
                ->map(fn($d) => ['id' => $d->id, 'title' => $d->title, 'department_id' => $d->department_id]),
        ]);
    }

    private function format(Employee $e, bool $full = false): array
    {
        $data = [
            'id'              => $e->id,
            'emp_number'      => $e->emp_number,
            'full_name'       => $e->full_name,
            'first_name'      => $e->first_name,
            'last_name'       => $e->last_name,
            'avatar_url'      => $e->user?->avatar_url,
            'department'      => $e->department?->name,
            'department_id'   => $e->department_id,
            'designation'     => $e->designation?->title ?? null,
            'designation_id'  => $e->designation_id,
            'status'          => $e->status,
            'employment_type' => $e->employment_type,
            'hire_date'       => $e->hire_date?->format('Y-m-d'),
            'phone'           => $e->phone,
        ];

        if ($full) {
            $data += [
                'email'          => $e->user?->email,
                'personal_email' => $e->personal_email,
                'gender'         => $e->gender,
                'date_of_birth'  => $e->date_of_birth?->format('Y-m-d'),
                'address'        => $e->address,
                'city'           => $e->city,
                'country'        => $e->country,
                'manager'        => $e->manager?->full_name,
                'bio'            => $e->bio,
            ];
        }

        return $data;
    }
}
