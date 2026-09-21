<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Changing a leave that has already been granted.
 *
 * Somebody is needed back early, or asks for two more days. The days are
 * recounted the way the application form counts them, and the balance moves by
 * the difference only — never recomputed from the request, because the same
 * balance carries every other leave that person has taken this year.
 */
class LeaveRecallAndAdjustTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private Employee $employee;
    private LeaveType $type;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'manager', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->hr = User::factory()->create();
        $this->hr->assignRole('hr-admin');

        $this->employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'emp_number' => 'HQ001',
            'first_name' => 'Ian',
            'last_name' => 'Kirabo',
            'hire_date' => '2024-01-15',
            'status' => 'on_leave',
        ]);

        $this->type = LeaveType::firstOrCreate(
            ['name' => 'Annual Leave'],
            ['code' => 'ANNUAL', 'days_allowed' => 21, 'is_active' => true]
        );
    }

    private function leave(string $from, string $to, float $days, string $status = 'approved'): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'from_date'     => $from,
            'to_date'       => $to,
            'days_count'    => $days,
            'reason'        => 'Family commitment.',
            'status'        => $status,
        ]);
    }

    private function balance(float $used = 0, float $pending = 0): LeaveBalance
    {
        return LeaveBalance::create([
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'year'          => (int) now()->year,
            'total_days'    => 21,
            'used_days'     => $used,
            'pending_days'  => $pending,
        ]);
    }

    // ── Recall ───────────────────────────────────────────────────────────

    public function test_recalling_ends_the_leave_today(): void
    {
        $leave = $this->leave(now()->subDays(2)->toDateString(), now()->addDays(3)->toDateString(), 6);

        $this->actingAs($this->hr)
            ->post(route('leaves.recall', $leave))
            ->assertRedirect();

        $this->assertSame(now()->toDateString(), $leave->refresh()->to_date->toDateString());
        $this->assertNotNull($leave->recalled_at);
        $this->assertSame($this->hr->id, $leave->recalled_by);
    }

    public function test_recalling_puts_the_person_back_on_duty(): void
    {
        $leave = $this->leave(now()->subDay()->toDateString(), now()->addDays(3)->toDateString(), 5);

        $this->actingAs($this->hr)->post(route('leaves.recall', $leave));

        $this->assertSame('active', $this->employee->refresh()->status);
    }

    public function test_the_unused_days_go_back_to_the_balance(): void
    {
        $balance = $this->balance(used: 6);
        $leave = $this->leave(now()->subDays(2)->toDateString(), now()->addDays(3)->toDateString(), 6);

        $this->actingAs($this->hr)->post(route('leaves.recall', $leave));

        $taken = (float) $leave->refresh()->days_count;

        $this->assertLessThan(6, $taken, 'The leave should be shorter than it was.');
        $this->assertEqualsWithDelta(6 - (6 - $taken), (float) $balance->refresh()->used_days, 0.01);
    }

    /** A leave ending before it begins is not a shortened leave. */
    public function test_recalling_before_it_starts_cancels_it(): void
    {
        $balance = $this->balance(used: 4);
        $leave = $this->leave(now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString(), 4);

        $this->actingAs($this->hr)->post(route('leaves.recall', $leave));

        $this->assertSame('cancelled', $leave->refresh()->status);
        $this->assertEqualsWithDelta(0, (float) $balance->refresh()->used_days, 0.01);
        $this->assertSame('active', $this->employee->refresh()->status);
    }

    public function test_only_an_approved_leave_can_be_recalled(): void
    {
        $leave = $this->leave(now()->toDateString(), now()->addDays(2)->toDateString(), 3, 'pending');

        $this->actingAs($this->hr)->post(route('leaves.recall', $leave));

        $this->assertNull($leave->refresh()->recalled_at);
    }

    // ── Extending and shortening ─────────────────────────────────────────

    public function test_extending_adds_the_extra_days_to_the_balance(): void
    {
        $balance = $this->balance(used: 2);
        // Mon-Tue, two working days.
        $leave = $this->leave('2026-10-05', '2026-10-06', 2);

        $this->actingAs($this->hr)
            ->post(route('leaves.adjust', $leave), ['to_date' => '2026-10-08'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(4, (float) $leave->refresh()->days_count, 0.01);
        $this->assertEqualsWithDelta(4, (float) $balance->refresh()->used_days, 0.01);
    }

    public function test_shortening_gives_the_days_back(): void
    {
        $balance = $this->balance(used: 4);
        $leave = $this->leave('2026-10-05', '2026-10-08', 4);

        $this->actingAs($this->hr)
            ->post(route('leaves.adjust', $leave), ['to_date' => '2026-10-06']);

        $this->assertEqualsWithDelta(2, (float) $leave->refresh()->days_count, 0.01);
        $this->assertEqualsWithDelta(2, (float) $balance->refresh()->used_days, 0.01);
    }

    /** The span that was actually approved is kept, not the last adjustment. */
    public function test_the_original_span_is_remembered_once(): void
    {
        $leave = $this->leave('2026-10-05', '2026-10-08', 4);

        $this->actingAs($this->hr)->post(route('leaves.adjust', $leave), ['to_date' => '2026-10-06']);
        $this->actingAs($this->hr)->post(route('leaves.adjust', $leave), ['to_date' => '2026-10-07']);

        $this->assertSame('2026-10-08', $leave->refresh()->original_to_date->toDateString());
        $this->assertEqualsWithDelta(4, (float) $leave->original_days, 0.01);
    }

    /** A person cannot take more days than they have. */
    public function test_extending_past_the_entitlement_is_refused(): void
    {
        $this->balance(used: 20);           // 21 entitled, 1 left
        $leave = $this->leave('2026-10-05', '2026-10-06', 2);

        $this->actingAs($this->hr)
            ->post(route('leaves.adjust', $leave), ['to_date' => '2026-10-16'])
            ->assertSessionHas('error');

        $this->assertEqualsWithDelta(2, (float) $leave->refresh()->days_count, 0.01);
    }

    public function test_the_leave_cannot_end_before_it_starts(): void
    {
        $leave = $this->leave('2026-10-05', '2026-10-08', 4);

        $this->actingAs($this->hr)
            ->post(route('leaves.adjust', $leave), ['to_date' => '2026-10-01'])
            ->assertSessionHasErrors('to_date');
    }

    // ── A rejected request must release what it reserved ─────────────────

    /**
     * A waiting request holds its days against the balance. Rejecting it
     * released nothing, so the days stayed held for the rest of the year.
     */
    public function test_rejecting_a_pending_leave_releases_its_reserved_days(): void
    {
        $balance = $this->balance(pending: 3);
        $leave = $this->leave('2026-10-05', '2026-10-07', 3, 'pending');

        $this->actingAs($this->hr)
            ->post(route('leaves.reject', $leave), ['rejection_reason' => 'Not this week.']);

        $this->assertEqualsWithDelta(0, (float) $balance->refresh()->pending_days, 0.01);
    }

    public function test_rejecting_an_approved_leave_still_returns_used_days(): void
    {
        $balance = $this->balance(used: 3);
        $leave = $this->leave('2026-10-05', '2026-10-07', 3);

        $this->actingAs($this->hr)
            ->post(route('leaves.reject', $leave), ['rejection_reason' => 'Withdrawn.']);

        $this->assertEqualsWithDelta(0, (float) $balance->refresh()->used_days, 0.01);
    }

    // ── Who may do it ────────────────────────────────────────────────────

    public function test_an_ordinary_employee_cannot_recall_or_adjust(): void
    {
        $leave = $this->leave(now()->toDateString(), now()->addDays(3)->toDateString(), 4);

        $staff = $this->employee->user;
        $staff->assignRole('employee');

        $this->actingAs($staff)->post(route('leaves.recall', $leave))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('leaves.adjust', $leave), ['to_date' => now()->addDays(9)->toDateString()])
            ->assertForbidden();

        $this->assertNull($leave->refresh()->recalled_at);
    }

    // ── The screen ───────────────────────────────────────────────────────

    /**
     * Str::singular('leaves') is 'leaf', so Route::resource bound {leaf} while
     * every controller method type-hints $leave. The names never matched, so
     * implicit binding injected an empty model and the detail page showed
     * "Unknown Employee", no dates and 0 day(s) for every request ever opened.
     */
    public function test_the_detail_page_loads_the_actual_leave(): void
    {
        $leave = $this->leave('2026-10-05', '2026-10-08', 4);

        $this->actingAs($this->hr)
            ->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('Ian Kirabo')
            ->assertDontSee('Unknown Employee');
    }

    public function test_the_page_shows_the_balance_and_the_controls(): void
    {
        $this->balance(used: 6, pending: 2);
        $leave = $this->leave(now()->toDateString(), now()->addDays(3)->toDateString(), 4);

        $this->actingAs($this->hr)
            ->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('Remaining')
            ->assertSee('Recall now')
            ->assertSee('Change the last day of leave');
    }

    public function test_an_employee_does_not_get_the_controls(): void
    {
        $leave = $this->leave(now()->toDateString(), now()->addDays(3)->toDateString(), 4);

        $staff = $this->employee->user;
        $staff->assignRole('employee');

        $this->actingAs($staff)
            ->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertDontSee('Recall now');
    }
}
