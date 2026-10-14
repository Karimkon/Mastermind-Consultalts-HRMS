<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\RoleAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The archive screen, and whether the record actually lasts.
 *
 * The durability tests here are the ones worth having. An archive that is
 * deleted along with the login it refers to is not an archive, and users are
 * hard deleted in this system -- App\Models\User has no SoftDeletes -- so a
 * cascading foreign key would have quietly erased exactly the history the
 * screen exists to show. The first version of the migration had one.
 */
class RoleArchiveScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RoleAssignments $roles;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'employee'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $this->admin = User::factory()->create(['status' => 'active', 'name' => 'The Admin']);
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin);

        $this->roles = app(RoleAssignments::class);
    }

    private function person(string $name): User
    {
        $u = User::factory()->create(['status' => 'active', 'name' => $name]);
        Employee::create([
            'user_id' => $u->id,
            'emp_number' => 'E'.$u->id,
            'first_name' => explode(' ', $name)[0],
            'last_name' => explode(' ', $name)[1] ?? 'Staff',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        return $u;
    }

    public function test_the_archive_lists_every_hr_the_company_has_had(): void
    {
        $ian = $this->person('Ian Kirabo');
        $grace = $this->person('Grace Nakato');

        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin', 'Left');
        $this->roles->grant($grace, 'hr-admin');

        $this->get(route('admin.roles.archive'))
            ->assertOk()
            ->assertSee('Role Archive')
            ->assertSee('Hr admin')
            ->assertSee('Grace Nakato')   // holds it
            ->assertSee('Ian Kirabo')     // held it
            ->assertSee('Previously');
    }

    public function test_the_archive_says_so_when_a_start_date_was_never_recorded(): void
    {
        // Backfilled rows have no start date on purpose. The screen must admit
        // that rather than render an empty cell.
        $ian = $this->person('Ian Kirabo');
        $this->roles->grant($ian, 'hr-admin');
        RoleAssignment::where('user_id', $ian->id)->update(['started_at' => null]);

        $this->get(route('admin.roles.archive'))
            ->assertOk()
            ->assertSee('no start date')
            ->assertSee('unknown – present');
    }

    public function test_an_ordinary_employee_cannot_read_the_archive(): void
    {
        $someone = $this->person('Just Staff');
        $someone->syncRoles(['employee']);

        $this->actingAs($someone)
            ->get(route('admin.roles.archive'))
            ->assertForbidden();
    }

    public function test_a_role_with_hundreds_of_holders_does_not_print_them_all(): void
    {
        /*
         | 1,242 people hold 'employee' on the live database. Rendering every
         | one of them made this page 545KB of names nobody came here to read.
         | The cap is on CURRENT holders only -- former holders are the archive
         | itself and stay complete.
         */
        for ($i = 1; $i <= 15; $i++) {
            $u = $this->person("Holder$i Staff");
            $this->roles->grant($u, 'employee');
        }

        $html = $this->get(route('admin.roles.archive'))->assertOk()->getContent();

        // Newest appointment first, so the 12 shown are Holder15 back to
        // Holder4 and the three oldest are the ones summarised.
        $this->assertStringContainsString('Holder15 Staff', $html);
        $this->assertStringContainsString('and 3 more holding it now', $html);
        $this->assertStringNotContainsString('Holder1 Staff<', $html);
    }

    public function test_former_holders_are_never_capped(): void
    {
        // The whole point of the archive: no "and 20 more" hiding the history.
        for ($i = 1; $i <= 15; $i++) {
            $u = $this->person("Past$i Holder");
            $this->roles->grant($u, 'hr-admin');
            $this->roles->revoke($u, 'hr-admin', 'Moved on');
        }

        $html = $this->get(route('admin.roles.archive'))->assertOk()->getContent();

        for ($i = 1; $i <= 15; $i++) {
            $this->assertStringContainsString("Past$i Holder", $html, "former holder $i is listed");
        }
    }

    // ───────────────────────────── does it last? ─────────────────────────────

    public function test_deleting_the_login_does_not_erase_the_history(): void
    {
        $ian = $this->person('Ian Kirabo');
        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin', 'Left the company');
        $id = $ian->id;

        Employee::where('user_id', $id)->forceDelete();
        User::find($id)->delete();

        $this->assertNull(User::find($id), 'the login really is gone');
        $this->assertSame(1, RoleAssignment::where('user_id', $id)->count(), 'the record is not');
        $this->assertSame('Ian Kirabo', RoleAssignment::where('user_id', $id)->first()->holderName());
    }

    public function test_the_archive_still_names_somebody_whose_account_was_deleted(): void
    {
        $ian = $this->person('Ian Kirabo');
        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin');

        Employee::where('user_id', $ian->id)->forceDelete();
        User::find($ian->id)->delete();

        $this->get(route('admin.roles.archive'))
            ->assertOk()
            ->assertSee('Ian Kirabo');
    }

    public function test_deleting_the_role_itself_does_not_erase_the_history(): void
    {
        // role_name is stored precisely so the record outlives the role. A
        // cascade on role_id would have made that storage pointless.
        $ian = $this->person('Ian Kirabo');
        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin');

        Role::findByName('hr-admin')->delete();

        $row = RoleAssignment::where('user_id', $ian->id)->first();

        $this->assertNotNull($row, 'the spell survives the role being deleted');
        $this->assertSame('hr-admin', $row->role_name);
    }

    public function test_the_name_recorded_is_the_one_they_had_at_the_time(): void
    {
        $ian = $this->person('Ian Kirabo');
        $this->roles->grant($ian, 'hr-admin');

        $this->assertSame('Ian Kirabo', RoleAssignment::where('user_id', $ian->id)->first()->user_name);
    }
}
