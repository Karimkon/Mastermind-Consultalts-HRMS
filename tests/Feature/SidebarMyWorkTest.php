<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Two account managers, two different sidebars.
 *
 * One account manager had a My Work section — My Profile, My Attendance, My
 * Leave, My Payslips, My Documents, Training, My Appraisal, My PIPs — and
 * another, doing the same job, had none of it. Reported from the live system
 * with screenshots of both.
 *
 * The block was `@role('employee')`. Holding a staff RECORD and holding the
 * employee ROLE are different things and nothing keeps them in step, so on
 * production six people with a staff record had no My Work at all: the MD, the
 * HR admin, the payroll officer, two of the six account managers, and one
 * person with no roles whatsoever.
 *
 * It is now gated on having a staff record, which is exactly what those pages
 * read. The role was never the right question.
 */
class SidebarMyWorkTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles, bool $withRecord): User
    {
        $u = User::factory()->create(['status' => 'active']);

        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
            $u->assignRole($r);
        }

        if ($withRecord) {
            Employee::create([
                'user_id' => $u->id,
                'emp_number' => 'E'.$u->id,
                'first_name' => 'Staff',
                'last_name' => 'Member',
                'hire_date' => now()->subYear(),
                'status' => 'active',
            ]);
        }

        return $u;
    }

    /** A page that renders the shared layout. */
    private function sidebarFor(User $u): string
    {
        return $this->actingAs($u)->get(route('profile'))->assertOk()->getContent();
    }

    public static function staffWhoShouldSeeIt(): array
    {
        return [
            'an account manager WITHOUT the employee role' => [['account-manager']],
            'an account manager WITH it' => [['account-manager', 'employee']],
            'the MD' => [['md', 'super-admin']],
            'HR' => [['hr-admin']],
            'the payroll officer' => [['payroll-officer']],
            'somebody with no roles at all' => [[]],
        ];
    }

    /**
     * @dataProvider staffWhoShouldSeeIt
     */
    public function test_anybody_with_a_staff_record_sees_my_work(array $roles): void
    {
        $this->assertStringContainsString(
            'My Work',
            $this->sidebarFor($this->user($roles, withRecord: true)),
            'a person with a staff record must be able to reach their own payslips and leave',
        );
    }

    public function test_two_account_managers_get_the_same_sidebar(): void
    {
        // The reported complaint, stated directly: same job, same navigation.
        $withRole = $this->user(['account-manager', 'employee'], withRecord: true);
        $withoutRole = $this->user(['account-manager'], withRecord: true);

        foreach (['My Work', 'My Leave', 'My Payslips', 'My Profile'] as $entry) {
            $this->assertStringContainsString($entry, $this->sidebarFor($withRole));
            $this->assertStringContainsString(
                $entry,
                $this->sidebarFor($withoutRole),
                "{$entry} is missing for the account manager without the employee role",
            );
        }
    }

    public function test_a_login_with_no_staff_record_does_not_get_it(): void
    {
        /*
         | A client portal login has no staff record, so every My Work link
         | would lead to a page with nothing behind it. Showing the section and
         | letting them find out is worse than not showing it.
         */
        $client = $this->user(['client'], withRecord: false);

        $this->assertStringNotContainsString('My Work', $this->sidebarFor($client));
    }

    public function test_the_account_manager_section_is_unaffected(): void
    {
        $am = $this->user(['account-manager'], withRecord: true);

        Client::create([
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Someone',
            'account_manager_id' => $am->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $sidebar = $this->sidebarFor($am);

        $this->assertStringContainsString('Leave Management', $sidebar);
        $this->assertStringContainsString('Payroll Runs', $sidebar);
    }
}
