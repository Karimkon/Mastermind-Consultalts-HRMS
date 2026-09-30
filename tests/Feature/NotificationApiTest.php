<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the phone gets back when it asks for notifications.
 *
 * The app was reading `title` and `body` out of the `data` blob; this project
 * keeps them as columns, and the endpoint sent neither — so every notice
 * arrived on the phone with no words in it and the app fell back to
 * prettifying the type string. "Mark all read" called a method that only
 * exists on Laravel's own notification collection, so it was a 500.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('employee', 'web');
        $u = User::factory()->create();
        $u->assignRole('employee');

        return $u;
    }

    private function notify(User $user, string $type, string $title = 'Something happened'): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $title,
            'body'    => 'The details of the thing.',
            'data'    => ['url' => 'https://mastermind.autos/pips/1'],
        ]);
    }

    public function test_a_notice_arrives_with_words_in_it(): void
    {
        $user = $this->user();
        $this->notify($user, 'pip', 'Performance Improvement Plan');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Performance Improvement Plan')
            ->assertJsonPath('data.data.0.body', 'The details of the thing.')
            ->assertJsonPath('data.data.0.type', 'pip')
            ->assertJsonPath('data.data.0.area', 'pips')
            ->assertJsonPath('data.data.0.action_url', 'https://mastermind.autos/pips/1');
    }

    public function test_the_counts_come_back_per_menu_area(): void
    {
        $user = $this->user();
        $this->notify($user, 'pip');
        $this->notify($user, 'pip');
        $this->notify($user, 'payroll_processed');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 3)
            ->assertJsonPath('areas.pips', 2)
            ->assertJsonPath('areas.payslips', 1);
    }

    public function test_mark_all_read_no_longer_throws(): void
    {
        $user = $this->user();
        $this->notify($user, 'pip');
        $this->notify($user, 'payroll_processed');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read', ['all' => true])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame([], Notification::unreadCountsFor($user->fresh()));
    }

    public function test_one_area_can_be_settled_on_its_own(): void
    {
        $user = $this->user();
        $this->notify($user, 'pip');
        $this->notify($user, 'payroll_processed');
        $this->notify($user, 'payroll_processed');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read', ['area' => 'pips'])
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('areas.payslips', 2)
            ->assertJsonMissingPath('areas.pips');
    }

    public function test_one_notice_can_still_be_marked_on_its_own(): void
    {
        $user = $this->user();
        $one  = $this->notify($user, 'pip');
        $this->notify($user, 'pip');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read', ['id' => $one->id])
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_one_persons_notices_never_reach_another(): void
    {
        $mine   = $this->user();
        $theirs = $this->user();
        $this->notify($theirs, 'pip');

        $this->actingAs($mine, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'data.data');
    }
}
