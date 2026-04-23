<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{User, Department, Client, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminApiController extends Controller
{
    // ===================== USERS =====================
    public function usersIndex(Request $request)
    {
        $query = User::with('roles');
        if ($request->search) {
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            );
        }
        if ($request->role) {
            $query->role($request->role);
        }

        return response()->json([
            'data' => $query->latest()->paginate(20)->through(fn($u) => $this->formatUser($u)),
        ]);
    }

    public function usersStore(Request $request)
    {
        if (!$request->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role'     => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'status'   => 'active',
        ]);
        $user->assignRole($request->role);

        return response()->json(['data' => $this->formatUser($user->load('roles'))], 201);
    }

    public function usersShow(User $user)
    {
        return response()->json(['data' => $this->formatUser($user->load('roles'))]);
    }

    public function usersUpdate(Request $request, User $user)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only(['name','email','status']));

        if ($request->role && $request->user()->hasRole('super-admin')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json(['data' => $this->formatUser($user->fresh()->load('roles'))]);
    }

    public function usersDestroy(Request $request, User $user)
    {
        if (!$request->user()->hasRole('super-admin')) abort(403);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete yourself.'], 422);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }

    // ===================== DEPARTMENTS =====================
    public function departmentsIndex()
    {
        return response()->json([
            'data' => Department::withCount('employees')->orderBy('name')->get()->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->name,
                'code'           => $d->code,
                'employees_count'=> $d->employees_count,
            ]),
        ]);
    }

    public function departmentsStore(Request $request)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);
        $request->validate(['name' => 'required|string|max:255|unique:departments,name']);
        $dept = Department::create($request->only(['name','code','description']));
        return response()->json(['data' => ['id' => $dept->id, 'name' => $dept->name]], 201);
    }

    public function departmentsUpdate(Request $request, Department $department)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);
        $department->update($request->only(['name','code','description']));
        return response()->json(['data' => ['id' => $department->id, 'name' => $department->name]]);
    }

    public function departmentsDestroy(Request $request, Department $department)
    {
        if (!$request->user()->hasRole('super-admin')) abort(403);
        $department->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ===================== ROLES =====================
    public function roles()
    {
        return response()->json([
            'data' => Role::orderBy('name')->get()->map(fn($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'users_count' => User::role($r->name)->count(),
            ]),
        ]);
    }

    // ===================== AUDIT =====================
    public function audit(Request $request)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);

        $query = \App\Models\AuditLog::with('user')->latest();
        if ($request->user_id)   $query->where('user_id', $request->user_id);
        if ($request->action)    $query->where('action', 'like', "%{$request->action}%");
        if ($request->from)      $query->whereDate('created_at', '>=', $request->from);
        if ($request->to)        $query->whereDate('created_at', '<=', $request->to);

        return response()->json([
            'data' => $query->paginate(30)->through(fn($a) => [
                'id'         => $a->id,
                'user'       => $a->user?->name,
                'action'     => $a->action,
                'model'      => $a->model_type,
                'model_id'   => $a->model_id,
                'ip'         => $a->ip_address,
                'created_at' => $a->created_at?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    // ===================== CLIENTS =====================
    public function clientsIndex(Request $request)
    {
        $query = Client::with('user');
        if ($request->search) {
            $query->where(fn($q) => $q
                ->where('company_name', 'like', "%{$request->search}%")
                ->orWhere('contact_person', 'like', "%{$request->search}%")
            );
        }

        return response()->json([
            'data' => $query->latest()->paginate(20)->through(fn($c) => [
                'id'             => $c->id,
                'company_name'   => $c->company_name,
                'contact_person' => $c->contact_person,
                'email'          => $c->user?->email,
                'industry'       => $c->industry,
                'status'         => $c->status,
            ]),
        ]);
    }

    public function clientsStore(Request $request)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);

        $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:8',
        ]);

        $user = User::create([
            'name'     => $request->contact_person ?? $request->company_name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'status'   => 'active',
        ]);
        $user->assignRole('client');

        $client = Client::create($request->only(['company_name','contact_person','industry','address','notes']) + [
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        return response()->json(['data' => ['id' => $client->id, 'company_name' => $client->company_name]], 201);
    }

    public function clientsShow(Client $client)
    {
        $client->load(['user', 'employees.department', 'jobPostings']);
        return response()->json([
            'data' => [
                'id'             => $client->id,
                'company_name'   => $client->company_name,
                'contact_person' => $client->contact_person,
                'email'          => $client->user?->email,
                'industry'       => $client->industry,
                'address'        => $client->address,
                'status'         => $client->status,
                'notes'          => $client->notes,
                'employees'      => $client->employees->map(fn($e) => [
                    'id'         => $e->id,
                    'name'       => $e->full_name,
                    'department' => $e->department?->name,
                ]),
                'job_postings'   => $client->jobPostings->map(fn($j) => [
                    'id'    => $j->id,
                    'title' => $j->title,
                    'status'=> $j->status,
                ]),
            ],
        ]);
    }

    public function clientsUpdate(Request $request, Client $client)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);
        $client->update($request->only(['company_name','contact_person','industry','address','status','notes']));
        return response()->json(['data' => ['id' => $client->id]]);
    }

    public function clientsAssignEmployee(Request $request, Client $client)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);
        $request->validate(['employee_id' => 'required|exists:employees,id']);

        $client->employees()->syncWithoutDetaching([
            $request->employee_id => ['assigned_by' => $request->user()->id],
        ]);
        return response()->json(['message' => 'Assigned.']);
    }

    public function clientsUnassignEmployee(Client $client, Employee $employee)
    {
        $client->employees()->detach($employee->id);
        return response()->json(['message' => 'Unassigned.']);
    }

    public function clientsAssignJob(Request $request, Client $client)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin'])) abort(403);
        $request->validate(['job_posting_id' => 'required|exists:job_postings,id']);

        $client->jobPostings()->syncWithoutDetaching([
            $request->job_posting_id => ['assigned_by' => $request->user()->id],
        ]);
        return response()->json(['message' => 'Assigned.']);
    }

    public function clientsUnassignJob(Client $client, \App\Models\JobPosting $job)
    {
        $client->jobPostings()->detach($job->id);
        return response()->json(['message' => 'Unassigned.']);
    }

    private function formatUser(User $u): array
    {
        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'status'      => $u->status,
            'mfa_enabled' => $u->mfa_enabled ?? false,
            'roles'       => $u->getRoleNames(),
            'created_at'  => $u->created_at?->format('Y-m-d'),
        ];
    }
}
