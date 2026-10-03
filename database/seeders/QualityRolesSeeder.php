<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Quality Management roles & permissions.
 *
 * Deliberately ADDITIVE: it creates the quality permissions and the two new
 * roles, and GRANTS the quality permissions to existing roles with
 * givePermissionTo (never syncPermissions), so nothing already assigned to
 * super-admin / hr-admin / manager is disturbed. Safe to run on production and
 * safe to re-run.
 */
class QualityRolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perms = ['quality.view', 'quality.manage', 'quality.audit'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // New roles.
        $qm = Role::firstOrCreate(['name' => 'quality-manager']);
        $qm->givePermissionTo(['quality.view', 'quality.manage', 'quality.audit', 'reports.view', 'employees.view']);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->givePermissionTo(['quality.view', 'quality.audit', 'employees.view']);

        // Grant quality access to the existing roles that already run the HRMS,
        // additively - their other permissions are untouched.
        foreach (['super-admin' => $perms, 'hr-admin' => ['quality.view', 'quality.manage', 'quality.audit'], 'manager' => ['quality.view']] as $role => $grant) {
            if ($r = Role::where('name', $role)->first()) {
                $r->givePermissionTo($grant);
            }
        }
    }
}
