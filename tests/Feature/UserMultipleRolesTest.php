<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A person can hold more than one job, and saying so must not cost them access.
 *
 * The Users screen took a single `role` and called syncRoles([$one]) on save.
 * syncRoles REPLACES, so editing anybody through that form silently stripped
 * every other role they held — and four people already hold several: the MD is
 * super-admin, md and auditor at once, and Mbogga Julius holds four. One
 * routine edit of a name or an email address would have quietly removed access
 * nobody asked to remove, with no warning and nothing in the interface to show
 * what had gone.
 *
 * The first test here is that specific loss. The rest are the feature asked
 * for: several roles per person, and direct permissions on top for the rare
 * exception a role cannot express.
 */
class UserMultipleRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'md', 'auditor', 'hr-admin', 'employee', 'quality-manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        foreach (['employees.view', 'payroll.run', 'reports.export'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super-admin');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Akol Deograceous',
            'email' => 'akol@mastermindconsults.co.ug',
            'status' => 'active',
            'roles' => ['super-admin'],
        ], $override);
    }

    // ───────────────────────────── the loss ─────────────────────────────

    public function test_editing_a_user_no_longer_strips_their_other_roles(): void
    {
        $md = User::factory()->create(['status' => 'active', 'name' => 'Akol']);
        $md->syncRoles(['super-admin', 'md', 'auditor']);

        // The ordinary case: somebody corrects a name and saves.
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $md), $this->payload([
                'name' => 'Akol Deograceous',
                'email' => $md->email,
                'roles' => ['super-admin', 'md', 'auditor'],
            ]))
            // Both assertions matter. Against the OLD controller the request
            // failed validation on a missing `role` and bounced back with the
            // roles untouched -- which looks identical to success if you only
            // check the roles afterwards. Proving the save actually HAPPENED
            // is what makes this test discriminate.
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            'Akol Deograceous',
            $md->fresh()->name,
            'the edit must have gone through, not been rejected',
        );

        $this->assertEqualsCanonicalizing(
            ['super-admin', 'md', 'auditor'],
            $md->fresh()->roles->pluck('name')->all(),
        );
    }

    // ──────────────────────────── the feature ────────────────────────────

    public function test_a_user_can_be_created_with_several_roles(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload([
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['employee', 'quality-manager', 'auditor'],
            ]))
            ->assertRedirect();

        $user = User::where('email', 'akol@mastermindconsults.co.ug')->first();

        $this->assertEqualsCanonicalizing(
            ['employee', 'quality-manager', 'auditor'],
            $user->roles->pluck('name')->all(),
        );
    }

    public function test_roles_can_be_added_to_somebody_who_had_one(): void
    {
        $u = User::factory()->create(['status' => 'active']);
        $u->syncRoles(['employee']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $u), $this->payload([
                'email' => $u->email,
                'roles' => ['employee', 'hr-admin'],
            ]))
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(['employee', 'hr-admin'], $u->fresh()->roles->pluck('name')->all());
    }

    public function test_a_role_can_be_taken_away(): void
    {
        // Sync still has to remove what was unticked, or nothing could ever be
        // revoked through the screen.
        $u = User::factory()->create(['status' => 'active']);
        $u->syncRoles(['employee', 'hr-admin']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $u), $this->payload([
                'email' => $u->email,
                'roles' => ['employee'],
            ]))
            ->assertRedirect();

        $this->assertSame(['employee'], $u->fresh()->roles->pluck('name')->all());
    }

    public function test_a_user_cannot_be_left_with_no_role_at_all(): void
    {
        // Somebody with no role sees almost nothing and cannot be told why.
        $u = User::factory()->create(['status' => 'active']);
        $u->syncRoles(['employee']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $u), $this->payload([
                'email' => $u->email,
                'roles' => [],
            ]))
            ->assertSessionHasErrors('roles');

        $this->assertSame(['employee'], $u->fresh()->roles->pluck('name')->all());
    }

    // ────────────────────── permissions on top of roles ──────────────────────

    public function test_a_permission_can_be_granted_directly(): void
    {
        $u = User::factory()->create(['status' => 'active']);
        $u->syncRoles(['employee']);

        $ids = Permission::whereIn('name', ['reports.export'])->pluck('id')->map(fn ($i) => (string) $i)->all();

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $u), $this->payload([
                'email' => $u->email,
                'roles' => ['employee'],
                'permissions' => $ids,
            ]))
            ->assertRedirect();

        $this->assertSame(['reports.export'], $u->fresh()->permissions->pluck('name')->all());
        $this->assertTrue($u->fresh()->hasPermissionTo('reports.export'));
    }

    public function test_a_permission_that_no_longer_exists_is_ignored_not_fatal(): void
    {
        // The form may have been open while somebody deleted a permission.
        $u = User::factory()->create(['status' => 'active']);
        $u->syncRoles(['employee']);

        $live = (string) Permission::where('name', 'payroll.run')->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $u), $this->payload([
                'email' => $u->email,
                'roles' => ['employee'],
                'permissions' => [$live, '999999'],
            ]))
            ->assertRedirect();

        $this->assertSame(['payroll.run'], $u->fresh()->permissions->pluck('name')->all());
    }

    public function test_the_forms_render_every_role_as_a_checkbox(): void
    {
        $u = User::factory()->create(['status' => 'active']);
        $u->syncRoles(['employee', 'auditor']);

        $body = $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $u))->assertOk()->content();

        // A dropdown could only ever show one of them.
        $this->assertStringContainsString('name="roles[]"', $body);
        $this->assertStringNotContainsString('name="role"', $body);
        $this->assertStringContainsString('name="permissions[]"', $body);
    }
}
