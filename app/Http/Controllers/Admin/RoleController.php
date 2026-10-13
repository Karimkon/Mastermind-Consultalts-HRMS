<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Every post, and everyone who has ever held it.
     *
     * The permission tables cannot answer this. model_has_roles holds three
     * columns and no dates, and taking a role away deletes the row, so a
     * departed HR leaves no trace there at all. This reads the appointment
     * record instead, which is written alongside every role change.
     */
    public function archive()
    {
        $roles = Role::orderBy('name')->get();

        // Grouped by name rather than id so a spell survives its role being
        // deleted and recreated, which is the reason role_name is stored.
        $history = RoleAssignment::with('user')
            ->orderByRaw('ended_at IS NOT NULL')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('role_name');

        // Appointments that predate the archive. Shown as a caveat rather than
        // quietly rendered as blanks.
        $undated = RoleAssignment::whereNull('started_at')->whereNull('ended_at')->count();
        $openedOn = RoleAssignment::whereNull('started_at')->min('created_at');

        return view('admin.roles.archive', [
            'roles' => $roles,
            'history' => $history,
            'undated' => $undated,
            'openedOn' => $openedOn ? Carbon::parse($openedOn) : null,
        ]);
    }

    public function edit(Role $role)
    {
        $allPermissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('.', $p->name)[0]; // group by module prefix
        });
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'allPermissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        /*
         | Resolved to Permission models before syncing, never passed through
         | as raw request input.
         |
         | The form checkboxes carry permission IDs, and a request always
         | delivers them as STRINGS. syncPermissions() treats a string as a
         | permission NAME, so saving any role died with
         |
         |     There is no permission named `8` for guard `web`
         |
         | and the page returned a 500. Nobody could change a role's
         | permissions at all.
         |
         | Fetching the models makes the intent unambiguous: these are ids.
         | It also drops any id that no longer exists rather than throwing,
         | which matters because the edit form may have been open while a
         | permission was removed.
         */
        $ids = array_filter(array_map('intval', (array) $request->input('permissions', [])));

        $permissions = Permission::whereIn('id', $ids)->get();

        $role->syncPermissions($permissions);

        return back()->with('success', "Permissions updated for '{$role->name}'.");
    }
}
