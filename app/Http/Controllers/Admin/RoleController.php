<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->get();

        return view('admin.roles.index', compact('roles'));
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
