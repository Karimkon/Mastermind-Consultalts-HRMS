<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\{Role, Permission};

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users','permissions'])->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        $allPermissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('.', $p->name)[0]; // group by module prefix
        });
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        return view('admin.roles.edit', compact('role','allPermissions','rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $permissions = $request->input('permissions', []);
        $role->syncPermissions($permissions);
        return back()->with('success', "Permissions updated for '{$role->name}'.");
    }
}
