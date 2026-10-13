<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Saving a role's permissions.
 *
 * Every attempt returned a 500. The edit form renders its checkboxes with the
 * permission ID as the value, a request delivers those as STRINGS, and
 * syncPermissions() reads a string as a permission NAME — so saving any role
 * at all died with:
 *
 *     There is no permission named `8` for guard `web`
 *
 * Three attempts are in the production log within a minute, all from the same
 * admin, which is what trying and retrying a broken button looks like.
 *
 * The ids are resolved to models now. These assert the round trip rather than
 * just a 200, because a 200 with nothing saved is the same bug wearing a
 * different face.
 */
class RolePermissionUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super-admin');

        $this->role = Role::findOrCreate('account-manager', 'web');

        foreach (['employees.view', 'employees.edit', 'payroll.run'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    public function test_permissions_can_be_saved_by_id(): void
    {
        $ids = Permission::whereIn('name', ['employees.view', 'payroll.run'])->pluck('id');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $this->role), [
                // Strings, exactly as a form posts them.
                'permissions' => $ids->map(fn ($i) => (string) $i)->toArray(),
            ])
            ->assertRedirect();

        $saved = $this->role->fresh()->permissions->pluck('name')->sort()->values()->toArray();

        $this->assertSame(['employees.view', 'payroll.run'], $saved);
    }

    public function test_unticking_everything_clears_them(): void
    {
        $this->role->syncPermissions(Permission::all());
        $this->assertCount(3, $this->role->fresh()->permissions);

        // An empty selection posts no `permissions` key at all.
        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $this->role), [])
            ->assertRedirect();

        $this->assertCount(0, $this->role->fresh()->permissions);
    }

    public function test_an_id_that_no_longer_exists_is_ignored_rather_than_fatal(): void
    {
        // The edit form may have been open while a permission was removed.
        $live = Permission::where('name', 'employees.view')->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $this->role), [
                'permissions' => [(string) $live, '999999'],
            ])
            ->assertRedirect();

        $this->assertSame(
            ['employees.view'],
            $this->role->fresh()->permissions->pluck('name')->toArray()
        );
    }

    public function test_the_edit_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.edit', $this->role))
            ->assertOk();
    }
}
