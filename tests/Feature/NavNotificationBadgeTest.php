<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The menu says where the unread thing is.
 *
 * The bell said "1" and nothing said which screen it was on, so somebody with
 * a plan opened, a payslip released and a leave request answered saw one
 * number and had to go looking. 439 payslip notices had gone unread on the
 * live system, because a payslip arriving looked exactly like nothing arriving.
 */
class NavNotificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create();
        $user->assignRole('employee');

        Employee::create([
            'user_id'    => $user->id,
            'emp_number' => 'MM' . $user->id,
            'first_name' => 'Sande',
            'last_name'  => 'Test',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);

        return $user;
    }

    private function notify(User $user, string $type, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'type'    => $type,
                'title'   => 'Something happened',
                'body'    => 'Details here',
            ]);
        }
    }

    // ── The counts ──────────────────────────────────────────────────────────

    public function test_unread_are_grouped_by_the_menu_item_they_belong_to(): void
    {
        $user = $this->employeeUser();

        $this->notify($user, 'pip', 2);
        $this->notify($user, 'payroll_processed', 3);
        $this->notify($user, 'leave_status');
        $this->notify($user, 'leave_submitted');     // same menu item as above
        $this->notify($user, 'meeting_invite');

        // Key order is whatever the grouped query returns and means nothing.
        $this->assertEquals([
            'pips'     => 2,
            'payslips' => 3,
            'leave'    => 2,   // leave_status + leave_submitted land on one item
            'calendar' => 1,
        ], Notification::unreadCountsFor($user));
    }

    public function test_read_ones_do_not_count(): void
    {
        $user = $this->employeeUser();
        $this->notify($user, 'pip', 3);

        Notification::where('user_id', $user->id)->limit(2)->update(['read_at' => now()]);

        $this->assertSame(['pips' => 1], Notification::unreadCountsFor($user));
    }

    public function test_one_persons_notices_never_show_on_anothers_menu(): void
    {
        $mine   = $this->employeeUser();
        $theirs = $this->employeeUser();
        $this->notify($theirs, 'pip', 4);

        $this->assertSame([], Notification::unreadCountsFor($mine));
    }

    public function test_a_type_with_no_menu_item_is_left_out(): void
    {
        $user = $this->employeeUser();
        $this->notify($user, 'chat', 5);          // the chat panel has its own
        $this->notify($user, 'pip');

        $this->assertSame(['pips' => 1], Notification::unreadCountsFor($user));
    }

    public function test_nobody_signed_in_gets_an_empty_set_not_an_error(): void
    {
        $this->assertSame([], Notification::unreadCountsFor(null));
    }

    // ── It reaches the page ─────────────────────────────────────────────────

    public function test_the_count_is_rendered_on_the_menu_item(): void
    {
        $user = $this->employeeUser();
        $this->notify($user, 'pip', 2);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-nav-badge="pips"', false);
    }

    // ── Opening the screen settles it ───────────────────────────────────────

    public function test_opening_the_screen_clears_that_badge_and_only_that_one(): void
    {
        $user = $this->employeeUser();
        $this->notify($user, 'pip', 2);
        $this->notify($user, 'payroll_processed', 3);

        $this->actingAs($user)->get('/pips')->assertOk();

        $this->assertSame(['payslips' => 3], Notification::unreadCountsFor($user),
            'Reading your improvement plans says nothing about your payslips.');
    }

    public function test_opening_an_unrelated_screen_clears_nothing(): void
    {
        $user = $this->employeeUser();
        $this->notify($user, 'pip', 2);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(['pips' => 2], Notification::unreadCountsFor($user));
    }

    public function test_the_bell_counts_past_the_ten_it_displays(): void
    {
        $user = $this->employeeUser();
        $this->notify($user, 'pip', 24);

        // The count used to be taken from the ten fetched for the panel, so it
        // stopped at 10 however many more arrived.
        $this->actingAs($user)->getJson('/ajax/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 24)
            ->assertJsonPath('areas.pips', 24)
            ->assertJsonCount(10, 'notifications');
    }
}
