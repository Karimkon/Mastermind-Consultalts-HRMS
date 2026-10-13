<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Archiving an employee can be undone.
 *
 * Employee has used SoftDeletes all along, so "Archive" never destroyed
 * anything — but no screen could see an archived person, which made an
 * accidental archive permanent in practice. It had to be found by reading the
 * database: an account manager was archived by mistake, kept her login and her
 * four client assignments, and simply vanished from the staff register with no
 * way to bring her back.
 *
 * Three records were sitting hidden like that.
 */
class EmployeeArchiveRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('hr-admin', 'web');
        $this->hr = User::factory()->create(['status' => 'active']);
        $this->hr->assignRole('hr-admin');
    }

    private function staff(string $first): Employee
    {
        return Employee::create([
            'emp_number' => 'E'.fake()->unique()->numberBetween(1000, 9999),
            'first_name' => $first,
            'last_name' => 'Worker',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
    }

    public function test_an_archived_employee_leaves_the_main_list(): void
    {
        $gone = $this->staff('Brenda');
        $this->staff('Shamilah');

        $gone->delete();

        $this->actingAs($this->hr)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Shamilah')
            ->assertDontSee('Brenda');
    }

    public function test_but_can_be_found_under_archived(): void
    {
        // The half that did not exist. Without it an archive is indistinguishable
        // from a deletion.
        $this->staff('Brenda')->delete();

        $this->actingAs($this->hr)
            ->get(route('employees.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Brenda');
    }

    public function test_the_archived_link_appears_only_when_there_is_something_in_it(): void
    {
        $this->actingAs($this->hr)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertDontSee('Archived (');

        $this->staff('Brenda')->delete();

        $this->actingAs($this->hr)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Archived (1)');
    }

    public function test_restoring_puts_them_back(): void
    {
        $gone = $this->staff('Brenda');
        $gone->delete();

        $this->actingAs($this->hr)
            ->post(route('employees.restore', $gone->id))
            ->assertRedirect();

        $this->assertNull($gone->fresh()->deleted_at);

        $this->actingAs($this->hr)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Brenda');
    }

    public function test_restoring_keeps_everything_that_was_attached(): void
    {
        /*
         | The reason archiving is a soft delete in the first place. A restored
         | person is the same record with the same id, so payroll history,
         | documents and leave are all still theirs -- nothing was ever
         | detached and nothing has to be reattached.
         */
        $gone = $this->staff('Brenda');
        $id = $gone->id;
        $number = $gone->emp_number;

        $gone->delete();

        $this->actingAs($this->hr)->post(route('employees.restore', $id))->assertRedirect();

        $back = Employee::find($id);

        $this->assertNotNull($back);
        $this->assertSame($number, $back->emp_number, 'the same record, not a new one');
        $this->assertSame('active', $back->status);
    }

    public function test_restoring_somebody_who_is_not_archived_is_not_found(): void
    {
        // Rather than silently succeeding and implying something happened.
        $live = $this->staff('Shamilah');

        $this->actingAs($this->hr)
            ->post(route('employees.restore', $live->id))
            ->assertNotFound();
    }
}
