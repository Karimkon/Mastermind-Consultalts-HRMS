<?php

namespace Tests\Feature;

use App\Mail\LeaveClientNotificationMail;
use App\Mail\LeaveSubmittedMail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Where a leave request is emailed, and who it is emailed to.
 *
 * Mastermind's own staff are attached to the HQ client exactly like a client
 * placement, because that is how payroll and the staff register find them. The
 * notification code read that as a placement and did two wrong things with an
 * account manager's own request:
 *
 *   - it emailed "their" account manager, meaning whoever holds the HQ client,
 *     which for one of them is their own second login;
 *   - it emailed the HQ client contact, as though Mastermind were its own
 *     customer.
 *
 * Nobody who could actually decide it was told. HR and the MD decide leave for
 * head-office staff, so they are who gets the email.
 */
class HeadOfficeLeaveNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Client $headOffice;

    private Client $customer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['employee', 'hr-admin', 'md', 'account-manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        LeaveType::firstOrCreate(['code' => 'ANNUAL'], ['name' => 'Annual Leave', 'days_allowed' => 21]);

        $this->headOffice = Client::create([
            'company_name' => 'Mastermind Consult Ltd HQ',
            'contact_person' => 'Someone',
            'user_id' => $this->userWith('employee', 'hq-portal@example.test')->id,
            'account_manager_id' => $this->userWith('account-manager', 'am@example.test')->id,
            'is_head_office' => true,
        ]);

        $this->customer = Client::create([
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Someone',
            'user_id' => $this->userWith('employee', 'roofings-portal@example.test')->id,
            'account_manager_id' => $this->userWith('account-manager', 'am2@example.test')->id,
            'is_head_office' => false,
        ]);
    }

    private function userWith(string $role, string $email): User
    {
        $u = User::factory()->create(['status' => 'active', 'email' => $email]);
        $u->assignRole($role);

        return $u;
    }

    private function staffAt(Client $client, string $email): User
    {
        $u = $this->userWith('employee', $email);

        $e = Employee::create([
            'user_id' => $u->id,
            'emp_number' => 'E'.$u->id,
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $client->employees()->attach($e->id, ['assigned_by' => $u->id]);

        // Somebody to nominate as cover.
        Employee::create([
            'emp_number' => 'C'.$u->id,
            'first_name' => 'Cover',
            'last_name' => 'Person',
            'hire_date' => now()->subYear(),
            'status' => 'active',
            'personal_email' => 'cover'.$u->id.'@example.test',
        ]);

        return $u;
    }

    private function apply(User $as): void
    {
        $cover = Employee::where('first_name', 'Cover')->latest('id')->first();

        $this->actingAs($as)->post(route('leaves.store'), [
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'),
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Personal',
            'replacement_employee_id' => $cover->id,
        ]);
    }

    public function test_head_office_leave_is_emailed_to_hr_and_the_md(): void
    {
        Mail::fake();

        $hr = $this->userWith('hr-admin', 'hr@example.test');
        $md = $this->userWith('md', 'md@example.test');

        $this->apply($this->staffAt($this->headOffice, 'brenda@example.test'));

        foreach ([$hr->email, $md->email] as $email) {
            Mail::assertSent(LeaveSubmittedMail::class, fn ($m) => $m->hasTo($email));
        }
    }

    public function test_and_not_to_the_hq_client_contact(): void
    {
        // Mastermind is not its own customer.
        Mail::fake();

        $this->userWith('hr-admin', 'hr@example.test');
        $this->apply($this->staffAt($this->headOffice, 'brenda@example.test'));

        Mail::assertNotSent(LeaveClientNotificationMail::class);
    }

    public function test_nor_to_whoever_happens_to_hold_the_hq_client(): void
    {
        /*
         | The sharpest edge. One account manager holds the HQ client under a
         | second login, so without this her own request was emailed to herself
         | as its approver.
         */
        Mail::fake();

        $this->userWith('hr-admin', 'hr@example.test');
        $this->apply($this->staffAt($this->headOffice, 'brenda@example.test'));

        Mail::assertNotSent(
            LeaveSubmittedMail::class,
            fn ($m) => $m->hasTo('am@example.test')
        );
    }

    public function test_a_placed_employee_still_follows_the_client_flow(): void
    {
        // The change must not disturb the ordinary case, which is most of them.
        Mail::fake();

        $this->apply($this->staffAt($this->customer, 'placed@example.test'));

        Mail::assertSent(LeaveSubmittedMail::class, fn ($m) => $m->hasTo('am2@example.test'));
        Mail::assertSent(LeaveClientNotificationMail::class);
    }
}
