<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Timestamps the mobile app reads must say which zone they are in.
 *
 * The app showed a message sent a minute earlier as "3h ago". The API sent
 * "2026-10-13 14:26:00" with no zone; Dart's DateTime.parse reads an unmarked
 * string as LOCAL time, the server stores UTC, and the phone is on EAT
 * (UTC+3). So the instant was read three hours earlier than it happened --
 * every timestamp in the app was stale by exactly the offset.
 *
 * Three hours is the tell. It is not drift, it is a missing zone.
 */
class ApiTimestampsCarryAZoneTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function notify(User $user): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'message_received',
            'title' => 'New message',
            'body' => 'System Admin: System ready over',
        ]);
    }

    public function test_the_notification_timestamp_carries_an_offset(): void
    {
        $user = $this->user();
        $this->notify($user);

        $row = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->json('data.data.0');

        $this->assertNotNull($row['created_at']);

        // Either a +hh:mm offset or a trailing Z. Anything else is ambiguous to
        // the client, which is the whole bug.
        $this->assertMatchesRegularExpression(
            '/(Z|[+-]\d{2}:\d{2})$/',
            $row['created_at'],
            'created_at must name its zone, got: '.$row['created_at'],
        );
    }

    public function test_a_notice_sent_now_does_not_read_as_hours_old(): void
    {
        /*
         | The user-visible symptom, asserted directly. Parsing the string the
         | way a client does and comparing it to now must land within a minute,
         | not three hours.
         */
        $user = $this->user();
        $this->notify($user);

        $sent = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->json('data.data.0.created_at');

        $parsed = Carbon::parse($sent);

        $this->assertLessThan(
            60,
            abs($parsed->diffInSeconds(now())),
            'a notice sent just now came back as '.$parsed->diffInSeconds(now()).'s old',
        );
    }

    public function test_the_zone_survives_a_different_display_timezone(): void
    {
        // The instant is the instant; the offset may differ.
        Config::set('app.display_timezone', 'Africa/Kampala');

        $user = $this->user();
        $this->notify($user);

        $sent = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->json('data.data.0.created_at');

        $this->assertLessThan(60, abs(Carbon::parse($sent)->diffInSeconds(now())));
    }

    public function test_read_at_carries_a_zone_too(): void
    {
        $user = $this->user();
        $n = $this->notify($user);
        $n->update(['read_at' => now()]);

        $row = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->json('data.data.0');

        $this->assertNotNull($row['read_at']);
        $this->assertMatchesRegularExpression('/(Z|[+-]\d{2}:\d{2})$/', $row['read_at']);
    }
}
