<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The quality manager could not reach the screen he is appointed to run.
 *
 * Every route under /quality admits `quality-manager`, and
 * QualityDocumentController::canManage() accepts it too — so the permission was
 * right the whole time. The sidebar gate was not: it read
 * `@role('super-admin|hr-admin|manager')`, so the Quality group and its
 * Document Control link never rendered for him. Typing the URL worked. Clicking
 * was impossible, because there was nothing to click.
 *
 * His only visible entry was Company Documents, which points at the read-only
 * published library and offers no way to add anything. A permission nobody can
 * find is not a permission, and a green suite never noticed because every test
 * used actingAs() and went straight to the route.
 */
class QualityManagerNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate('employee', 'web');

        $user = User::create([
            'name'     => 'Test '.$role,
            'email'    => str_replace('-', '', $role).'@test.local',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole(['employee', $role]);

        return $user;
    }

    public function test_the_quality_manager_can_open_the_new_document_form(): void
    {
        $this->actingAs($this->userWithRole('quality-manager'))
            ->get('/quality/documents/create')
            ->assertOk();
    }

    public function test_the_quality_manager_can_open_document_control(): void
    {
        $this->actingAs($this->userWithRole('quality-manager'))
            ->get('/quality/documents')
            ->assertOk();
    }

    /** The point of the fix: the link is actually on the page. */
    public function test_the_quality_manager_is_given_a_link_to_document_control(): void
    {
        $response = $this->actingAs($this->userWithRole('quality-manager'))
            ->get('/quality/library')
            ->assertOk();

        $response->assertSee('Document Control', false);
        $response->assertSee('>Quality<', false);
    }

    /** An ordinary employee still gets the library and nothing more. */
    public function test_an_ordinary_employee_sees_no_quality_section(): void
    {
        Role::findOrCreate('employee', 'web');

        $user = User::create([
            'name'     => 'Ordinary',
            'email'    => 'ordinary@test.local',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole('employee');

        $response = $this->actingAs($user)->get('/quality/library')->assertOk();
        $response->assertDontSee('Document Control', false);

        $this->actingAs($user)->get('/quality/documents/create')->assertForbidden();
    }
}
