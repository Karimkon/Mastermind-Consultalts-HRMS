<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RoleAssignments;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->role, fn ($q) => $q->role($request->role))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->paginate(20);
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create()
    {
        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'permissions' => $this->groupedPermissions(),
        ]);
    }

    /**
     * Permissions grouped by the module prefix in their name.
     *
     * Same grouping the role editor uses, so the two screens read alike.
     */
    private function groupedPermissions()
    {
        return Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]);
    }

    /**
     * The roles and permissions a form sent, resolved to real records.
     *
     * Roles arrive as names and permissions as ids, because that is what the
     * two pickers emit. Anything that no longer exists is dropped rather than
     * throwing: the form may have been open while somebody deleted a role.
     *
     * @return array{0: array<int, string>, 1: Collection}
     */
    private function resolveAccess(Request $request): array
    {
        $roles = Role::whereIn('name', (array) $request->input('roles', []))
            ->pluck('name')
            ->all();

        $ids = array_filter(array_map('intval', (array) $request->input('permissions', [])));
        $permissions = Permission::whereIn('id', $ids)->get();

        return [$roles, $permissions];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'status' => 'required',
            // Plural, and at least one. Somebody can hold several jobs here:
            // four people already do, including the MD.
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'permissions' => 'nullable|array',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
        ]);

        [$roles, $permissions] = $this->resolveAccess($request);

        // Through the service so the appointment is recorded. syncRoles is
        // still what decides access; this only writes the history beside it.
        app(RoleAssignments::class)->sync($user, $roles, 'Set when the account was created');
        $user->syncPermissions($permissions);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'permissions' => $this->groupedPermissions(),
            'heldRoles' => $user->roles->pluck('name')->all(),
            // Permissions granted to this person directly, NOT the ones they
            // get through a role -- those are shown but not editable here.
            'heldPermissions' => $user->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'status' => 'required',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'permissions' => 'nullable|array',
        ]);
        $user->update(['name' => $data['name'], 'email' => $data['email'], 'status' => $data['status']]);
        if ($request->password) {
            $request->validate(['password' => 'min:8|confirmed']);
            $user->update(['password' => Hash::make($request->password)]);
        }
        /*
         | syncRoles used to be handed a single role, so saving ANY user through
         | this form silently stripped every other role they held. Four people
         | are affected today -- the MD holds super-admin, md and auditor, and
         | Mbogga Julius holds four -- so one routine edit would have quietly
         | taken away access nobody asked to remove.
         */
        [$roles, $permissions] = $this->resolveAccess($request);

        app(RoleAssignments::class)->sync($user, $roles, $request->input('role_reason'));
        $user->syncPermissions($permissions);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot delete yourself.');
        }
        $user->delete();

        return back()->with('success', 'User deleted.');
    }
}
