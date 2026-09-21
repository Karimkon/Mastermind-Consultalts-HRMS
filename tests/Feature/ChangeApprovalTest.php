<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Notification;
use App\Models\PendingChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An account manager proposes; HR decides.
 *
 * An account manager edits the records of people they place at a client, and
 * those fields move money — a bank account number, a payment mode, a salary.
 * Nothing they change is written until HR approves it, and every step leaves an
 * audit row.
 */
class ChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $am;
    private User $hr;
    private Client $client;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['account-manager', 'hr-admin', 'super-admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->am = User::factory()->create(['name' => 'Brenda Kansiime']);
        $this->am->assignRole('account-manager');

        $this->hr = User::factory()->create(['name' => 'Ian Kirabo']);
        $this->hr->assignRole('hr-admin');

        $this->client = Client::create([
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Someone',
            'account_manager_id' => $this->am->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->employee = Employee::create([
            'emp_number' => 'RUL001',
            'first_name' => 'Sande',
            'last_name' => 'Alamanzani',
            'hire_date' => '2024-01-15',
            'status' => 'active',
            'bank_account' => '0140011111111',
        ]);

        $this->client->employees()->attach($this->employee->id, ['assigned_by' => $this->am->id]);
    }

    private function edit(array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->am)->put(
            route('account-manager.employees.update', $this->employee),
            array_merge([
                'first_name' => $this->employee->first_name,
                'last_name'  => $this->employee->last_name,
            ], $this->currentFlags(), $fields)
        );
    }

    /**
     * The five statutory checkboxes the form renders on every save.
     *
     * A checkbox posts nothing when unticked, so the browser always sends these
     * at their current value. A fixture that leaves them out is proposing to
     * switch all five off, which is not what a save does.
     */
    private function currentFlags(): array
    {
        $flags = [];

        foreach (['charge_paye', 'charge_nssf', 'nssf_paid_by_employer', 'charge_lst', 'tax_paid_by_employer'] as $flag) {
            if ($this->employee->fresh()->$flag) {
                $flags[$flag] = '1';
            }
        }

        return $flags;
    }

    // ── Nothing happens until HR says so ─────────────────────────────────

    public function test_an_account_managers_edit_does_not_touch_the_record(): void
    {
        $this->edit(['bank_account' => '0140099999999'])->assertRedirect();

        $this->assertSame('0140011111111', $this->employee->refresh()->bank_account,
            'The account number changed without HR approving it.');
    }

    public function test_the_edit_is_held_as_a_pending_change(): void
    {
        $this->edit(['bank_account' => '0140099999999']);

        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->assertSame($this->am->id, $pending->requested_by);
        $this->assertSame('0140099999999', $pending->payload['bank_account']);
        $this->assertSame('0140011111111', $pending->original_values['bank_account']);
        $this->assertSame($this->client->id, $pending->client_id);
    }

    /** Forty inputs, two that moved — a reviewer reads the two. */
    public function test_only_fields_that_actually_changed_are_recorded(): void
    {
        $this->edit(['bank_account' => '0140099999999', 'city' => null]);

        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->assertArrayHasKey('bank_account', $pending->payload);
        $this->assertArrayNotHasKey('first_name', $pending->payload,
            'An unchanged field was recorded as a change.');
    }

    public function test_an_edit_that_changes_nothing_creates_no_request(): void
    {
        $this->edit()->assertRedirect();

        $this->assertSame(0, PendingChange::where('model_type', 'Employee')->count());
    }

    // ── Approving ────────────────────────────────────────────────────────

    public function test_hr_approval_writes_the_change(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)
            ->post(route('admin.change-approvals.approve', $pending))
            ->assertRedirect();

        $this->assertSame('0140099999999', $this->employee->refresh()->bank_account);
        $this->assertSame(PendingChange::APPROVED, $pending->refresh()->status);
        $this->assertSame($this->hr->id, $pending->reviewed_by);
    }

    public function test_rejection_leaves_the_record_alone(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)
            ->post(route('admin.change-approvals.reject', $pending), ['review_note' => 'Wrong account.'])
            ->assertRedirect();

        $this->assertSame('0140011111111', $this->employee->refresh()->bank_account);
        $this->assertSame(PendingChange::REJECTED, $pending->refresh()->status);
        $this->assertSame('Wrong account.', $pending->review_note);
    }

    /** A refusal with no reason leaves the requester guessing and resubmitting. */
    public function test_a_rejection_must_carry_a_reason(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)
            ->post(route('admin.change-approvals.reject', $pending))
            ->assertSessionHasErrors('review_note');

        $this->assertTrue($pending->refresh()->isPending());
    }

    public function test_a_decided_change_cannot_be_decided_again(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)->post(route('admin.change-approvals.approve', $pending));
        $this->employee->update(['bank_account' => '0140055555555']);

        $this->actingAs($this->hr)->post(route('admin.change-approvals.approve', $pending));

        $this->assertSame('0140055555555', $this->employee->refresh()->bank_account,
            'An already-approved change was applied a second time.');
    }

    // ── Who may approve ──────────────────────────────────────────────────

    public function test_an_account_manager_cannot_approve_their_own_change(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->am)
            ->post(route('admin.change-approvals.approve', $pending))
            ->assertForbidden();

        $this->assertSame('0140011111111', $this->employee->refresh()->bank_account);
    }

    public function test_an_account_manager_cannot_open_the_queue(): void
    {
        $this->actingAs($this->am)
            ->get(route('admin.change-approvals.index'))
            ->assertForbidden();
    }

    /**
     * HR are the approvers, so their own edits go straight through. Routing
     * them into a queue only they can clear would leave nobody able to act.
     */
    public function test_hr_editing_directly_is_not_queued(): void
    {
        $this->actingAs($this->hr)->put(
            route('account-manager.employees.update', $this->employee),
            ['first_name' => 'Sande', 'last_name' => 'Alamanzani', 'bank_account' => '0140077777777']
        )->assertRedirect();

        $this->assertSame('0140077777777', $this->employee->refresh()->bank_account);
        $this->assertSame(0, PendingChange::count());
    }

    // ── The audit trail ──────────────────────────────────────────────────

    public function test_asking_approving_and_refusing_are_all_audited(): void
    {
        $this->edit(['bank_account' => '0140099999999']);

        $this->assertSame(1, AuditLog::where('action', 'change_requested')->count());

        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();
        $this->actingAs($this->hr)->post(route('admin.change-approvals.approve', $pending));

        $this->assertSame(1, AuditLog::where('action', 'change_approved')->count());

        $approved = AuditLog::where('action', 'change_approved')->firstOrFail();
        $this->assertSame($this->hr->id, $approved->user_id);
        $this->assertStringContainsString('0140099999999', $approved->new_values);
        $this->assertStringContainsString('0140011111111', $approved->old_values);
    }

    public function test_a_rejection_is_audited_too(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)
            ->post(route('admin.change-approvals.reject', $pending), ['review_note' => 'No.']);

        $this->assertSame(1, AuditLog::where('action', 'change_rejected')->count());
    }

    // ── People are told ──────────────────────────────────────────────────

    public function test_hr_is_notified_when_a_change_is_asked_for(): void
    {
        $this->edit(['bank_account' => '0140099999999']);

        $note = Notification::where('user_id', $this->hr->id)
            ->where('type', 'change_requested')->first();

        $this->assertNotNull($note, 'A queue nobody is told about is a queue nobody clears.');
        $this->assertStringContainsString('Brenda Kansiime', $note->body);
    }

    public function test_the_account_manager_is_told_the_outcome(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)
            ->post(route('admin.change-approvals.reject', $pending), ['review_note' => 'Wrong account.']);

        $note = Notification::where('user_id', $this->am->id)
            ->where('type', 'change_rejected')->first();

        $this->assertNotNull($note);
        $this->assertStringContainsString('Wrong account.', $note->body);
    }

    // ── Drift ────────────────────────────────────────────────────────────

    /**
     * A change approved a week late must not quietly undo an edit made in
     * between, so the reviewer is shown that the field has moved.
     */
    public function test_a_field_edited_since_the_request_is_flagged(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->employee->update(['bank_account' => '0140088888888']);

        $this->assertTrue($pending->refresh()->hasDrift());

        $this->actingAs($this->hr)
            ->get(route('admin.change-approvals.show', $pending))
            ->assertOk()
            ->assertSee('changed since the request');
    }

    public function test_an_untouched_field_is_not_flagged(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->assertFalse($pending->hasDrift());
    }

    // ── Salary rides the same rails ──────────────────────────────────────

    public function test_a_salary_change_also_waits_for_approval(): void
    {
        EmployeeSalary::create([
            'employee_id'    => $this->employee->id,
            'basic_salary'   => 500000,
            'salary_type'    => 'monthly',
            'effective_from' => '2026-01-01',
            'is_current'     => true,
        ]);

        $this->edit(['basic_salary' => 900000]);

        $this->assertEqualsWithDelta(500000, (float) $this->employee->refresh()->salary->basic_salary, 0.01,
            'A salary was raised without HR approving it.');

        $pending = PendingChange::pending()->where('model_type', 'EmployeeSalary')->firstOrFail();
        $this->assertSame('900000', (string) $pending->payload['basic_salary']);

        $this->actingAs($this->hr)->post(route('admin.change-approvals.approve', $pending));

        $this->assertEqualsWithDelta(900000, (float) $this->employee->refresh()->salary->basic_salary, 0.01);
    }

    // ── The screens ──────────────────────────────────────────────────────

    public function test_the_queue_shows_what_is_waiting(): void
    {
        $this->edit(['bank_account' => '0140099999999']);

        $this->actingAs($this->hr)
            ->get(route('admin.change-approvals.index'))
            ->assertOk()
            ->assertSee('Sande Alamanzani')
            ->assertSee('Brenda Kansiime');
    }

    public function test_the_review_screen_shows_before_and_after(): void
    {
        $this->edit(['bank_account' => '0140099999999']);
        $pending = PendingChange::pending()->where('model_type', 'Employee')->firstOrFail();

        $this->actingAs($this->hr)
            ->get(route('admin.change-approvals.show', $pending))
            ->assertOk()
            ->assertSee('0140011111111')
            ->assertSee('0140099999999')
            ->assertSee('Nothing below has been written yet');
    }

    public function test_the_account_manager_is_told_their_edit_is_waiting(): void
    {
        $this->edit(['bank_account' => '0140099999999']);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees.show', $this->employee))
            ->assertOk()
            ->assertSee('waiting for HR approval');
    }
}
