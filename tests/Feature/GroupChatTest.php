<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One message to a whole site, rather than the same sentence ninety times.
 *
 * The schema always allowed a group — conversations carry a type and a name,
 * and titleFor() already handled one — but nothing could create it, so every
 * thread in the system was between exactly two people.
 */
class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, string $name = 'Someone'): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create(['name' => $name, 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    private function employeeFor(User $user, ?int $managerId = null, ?int $departmentId = null): Employee
    {
        return Employee::create([
            'user_id'       => $user->id,
            'emp_number'    => 'MM' . $user->id,
            'first_name'    => $user->name,
            'last_name'     => 'Test',
            'manager_id'    => $managerId,
            'department_id' => $departmentId,
            'hire_date'     => now()->subYear(),
            'status'        => 'active',
        ]);
    }

    // ── Who may open one ────────────────────────────────────────────────────

    public function test_an_account_manager_can_open_a_group(): void
    {
        $am    = $this->userWithRole('account-manager', 'Omar');
        $staff = [
            $this->userWithRole('employee', 'Guard One')->id,
            $this->userWithRole('employee', 'Guard Two')->id,
        ];

        $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Serena night shift', 'user_ids' => $staff])
            ->assertCreated()
            ->assertJsonPath('title', 'Serena night shift')
            // The creator is always in it — a group you cannot see is one you
            // cannot follow up.
            ->assertJsonPath('members', 3);

        $group = Conversation::where('type', Conversation::GROUP)->first();
        $this->assertSame('Serena night shift', $group->name);
        $this->assertSame($am->id, $group->created_by);
        $this->assertTrue($group->participants()->where('user_id', $am->id)->exists());
    }

    public function test_hr_finance_md_and_managers_can_too(): void
    {
        foreach (['hr-admin', 'payroll-officer', 'md', 'manager', 'super-admin'] as $role) {
            $staff = $this->userWithRole('employee')->id;

            $this->actingAs($this->userWithRole($role))
                ->postJson('/chat/group', ['name' => "A {$role} group", 'user_ids' => [$staff]])
                ->assertCreated();
        }
    }

    public function test_an_ordinary_employee_cannot_open_one(): void
    {
        $other = $this->userWithRole('employee')->id;

        // Writing to a hundred phones at once is not the same act as messaging
        // a colleague.
        $this->actingAs($this->userWithRole('employee'))
            ->postJson('/chat/group', ['name' => 'Everyone', 'user_ids' => [$other]])
            ->assertForbidden();

        $this->assertSame(0, Conversation::where('type', Conversation::GROUP)->count());
    }

    public function test_a_group_needs_a_name_and_somebody_in_it(): void
    {
        $am = $this->userWithRole('account-manager');

        $this->actingAs($am)->postJson('/chat/group', ['user_ids' => [$this->userWithRole('employee')->id]])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->actingAs($am)->postJson('/chat/group', ['name' => 'Nobody here'])
            ->assertStatus(422)->assertJsonValidationErrors('user_ids');
    }

    // ── What it does ────────────────────────────────────────────────────────

    public function test_one_message_reaches_everybody_in_the_group(): void
    {
        $am      = $this->userWithRole('account-manager');
        $members = collect(range(1, 4))->map(fn () => $this->userWithRole('employee'));

        $id = $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Lubowa site', 'user_ids' => $members->pluck('id')->all()])
            ->json('conversation_id');

        $this->actingAs($am)->postJson("/chat/{$id}/send", ['body' => 'Payday moved to Friday.'])
            ->assertSuccessful();

        // Every member can read it, from their own account.
        foreach ($members as $member) {
            $this->actingAs($member)->getJson("/chat/{$id}/messages")
                ->assertOk()
                ->assertSee('Payday moved to Friday.');
        }
    }

    public function test_somebody_outside_the_group_cannot_read_it(): void
    {
        $am = $this->userWithRole('account-manager');
        $id = $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Private', 'user_ids' => [$this->userWithRole('employee')->id]])
            ->json('conversation_id');

        $this->actingAs($am)->postJson("/chat/{$id}/send", ['body' => 'Internal only.']);

        $this->actingAs($this->userWithRole('employee'))
            ->getJson("/chat/{$id}/messages")
            ->assertForbidden();
    }

    public function test_an_inactive_account_is_left_out(): void
    {
        $am     = $this->userWithRole('account-manager');
        $active = $this->userWithRole('employee');
        $gone   = $this->userWithRole('employee');
        $gone->update(['status' => 'inactive']);

        $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Site', 'user_ids' => [$active->id, $gone->id]])
            ->assertCreated()
            ->assertJsonPath('members', 2);   // the AM and the active one
    }

    public function test_the_group_is_named_the_same_for_everybody(): void
    {
        $am     = $this->userWithRole('account-manager');
        $member = $this->userWithRole('employee');

        $id = $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Bidco day shift', 'user_ids' => [$member->id]])
            ->json('conversation_id');

        // A direct thread is named after the other person, so it reads
        // differently to each side. A group has a name of its own.
        $group = Conversation::find($id);
        $this->assertSame('Bidco day shift', $group->titleFor($am->id));
        $this->assertSame('Bidco day shift', $group->titleFor($member->id));
    }

    // ── Adding people later ─────────────────────────────────────────────────

    public function test_more_people_can_be_added_and_duplicates_are_skipped(): void
    {
        $am    = $this->userWithRole('account-manager');
        $first = $this->userWithRole('employee');
        $later = $this->userWithRole('employee');

        $id = $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Site', 'user_ids' => [$first->id]])
            ->json('conversation_id');

        $this->actingAs($am)
            ->postJson("/chat/{$id}/members", ['user_ids' => [$later->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('added', 1)
            ->assertJsonPath('members', 3);
    }

    public function test_somebody_outside_the_group_cannot_add_to_it(): void
    {
        $am = $this->userWithRole('account-manager');
        $id = $this->actingAs($am)
            ->postJson('/chat/group', ['name' => 'Site', 'user_ids' => [$this->userWithRole('employee')->id]])
            ->json('conversation_id');

        $this->actingAs($this->userWithRole('hr-admin'))
            ->postJson("/chat/{$id}/members", ['user_ids' => [$this->userWithRole('employee')->id]])
            ->assertForbidden();
    }

    // ── The ready-made lists ────────────────────────────────────────────────

    public function test_an_account_manager_is_offered_their_own_clients_only(): void
    {
        $am    = $this->userWithRole('account-manager');
        $other = $this->userWithRole('account-manager');

        $mine = Client::create(['company_name' => 'Mine Ltd', 'contact_person' => 'A',
            'user_id' => $this->userWithRole('client')->id, 'account_manager_id' => $am->id]);
        $theirs = Client::create(['company_name' => 'Theirs Ltd', 'contact_person' => 'B',
            'user_id' => $this->userWithRole('client')->id, 'account_manager_id' => $other->id]);

        $onMine = $this->employeeFor($this->userWithRole('employee'));
        $onMine->clients()->attach($mine->id, ['assigned_by' => $am->id]);

        $onTheirs = $this->employeeFor($this->userWithRole('employee'));
        $onTheirs->clients()->attach($theirs->id, ['assigned_by' => $other->id]);

        $labels = collect($this->actingAs($am)->getJson('/chat/audiences')->assertOk()->json('audiences'))
            ->pluck('label');

        $this->assertContains('Mine Ltd', $labels);
        $this->assertNotContains('Theirs Ltd', $labels);
    }

    public function test_hr_is_offered_departments_too(): void
    {
        $hr         = $this->userWithRole('hr-admin');
        $department = Department::create(['name' => 'Security', 'code' => 'SEC']);
        $this->employeeFor($this->userWithRole('employee'), null, $department->id);

        $audiences = collect($this->actingAs($hr)->getJson('/chat/audiences')->assertOk()->json('audiences'));

        $security = $audiences->firstWhere('label', 'Security');
        $this->assertNotNull($security);
        $this->assertSame('Departments', $security['group']);
        $this->assertSame(1, $security['count']);
    }

    public function test_a_manager_is_offered_their_reporting_line(): void
    {
        $bossUser = $this->userWithRole('manager', 'Boss');
        $boss     = $this->employeeFor($bossUser);
        $this->employeeFor($this->userWithRole('employee'), $boss->id);

        $team = collect($this->actingAs($bossUser)->getJson('/chat/audiences')->assertOk()->json('audiences'))
            ->firstWhere('key', 'my-team');

        $this->assertNotNull($team);
        $this->assertSame(1, $team['count']);
    }

    public function test_an_employee_is_offered_nothing(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->getJson('/chat/audiences')
            ->assertForbidden();
    }
}
