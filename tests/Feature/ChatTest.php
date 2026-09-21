<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff messaging.
 *
 * The part that matters most is who can read what. Attachments are served
 * through the application rather than from a public path, so a voice note
 * between two people cannot be fetched by anyone who comes by the URL.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $ian;
    private User $akol;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('employee', 'web');
        Storage::fake('local');

        $this->ian = User::factory()->create(['name' => 'Ian Kirabo', 'status' => 'active']);
        $this->akol = User::factory()->create(['name' => 'Akol Deograceous', 'status' => 'active']);
        $this->stranger = User::factory()->create(['name' => 'Nobody', 'status' => 'active']);
    }

    private function thread(): Conversation
    {
        return Conversation::between($this->ian->id, $this->akol->id);
    }

    private function send(User $as, Conversation $c, array $payload)
    {
        return $this->actingAs($as)->post(route('chat.send', $c), $payload);
    }

    // ── Threads ──────────────────────────────────────────────────────────

    public function test_two_people_get_one_thread_not_two(): void
    {
        $first = Conversation::between($this->ian->id, $this->akol->id);
        // Opened from the other end, which is how a second thread would appear.
        $second = Conversation::between($this->akol->id, $this->ian->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_a_thread_is_named_after_the_other_person(): void
    {
        $c = $this->thread();

        $this->assertSame('Akol Deograceous', $c->titleFor($this->ian->id));
        $this->assertSame('Ian Kirabo', $c->titleFor($this->akol->id));
    }

    public function test_you_cannot_message_yourself(): void
    {
        $this->actingAs($this->ian)
            ->post(route('chat.with', $this->ian))
            ->assertStatus(422);
    }

    // ── Sending ──────────────────────────────────────────────────────────

    public function test_a_message_is_sent_and_read_back(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, ['body' => 'Are the payslips ready?'])->assertStatus(201);

        $this->actingAs($this->akol)
            ->get(route('chat.messages', $c))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Are the payslips ready?')
            ->assertJsonPath('messages.0.mine', false);
    }

    public function test_an_empty_message_is_refused(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, ['body' => '   '])->assertStatus(422);

        $this->assertSame(0, Message::count());
    }

    public function test_somebody_outside_the_thread_cannot_write_to_it(): void
    {
        $c = $this->thread();

        $this->send($this->stranger, $c, ['body' => 'Hello'])->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_somebody_outside_the_thread_cannot_read_it(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'Private']);

        $this->actingAs($this->stranger)
            ->get(route('chat.messages', $c))
            ->assertForbidden();
    }

    // ── Media ────────────────────────────────────────────────────────────

    public function test_a_photo_is_filed_as_an_image(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, [
            'attachment' => UploadedFile::fake()->image('site.jpg'),
        ])->assertStatus(201)->assertJsonPath('message.type', 'image');
    }

    public function test_a_voice_note_is_filed_as_audio(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, [
            'attachment' => UploadedFile::fake()->create('voice-note.webm', 200, 'audio/webm'),
            'duration'   => 12,
        ])->assertStatus(201)->assertJsonPath('message.type', 'audio');

        $this->assertSame(12, Message::first()->duration);
    }

    public function test_a_video_is_filed_as_video(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, [
            'attachment' => UploadedFile::fake()->create('handover.mp4', 500, 'video/mp4'),
        ])->assertStatus(201)->assertJsonPath('message.type', 'video');
    }

    public function test_anything_else_is_filed_as_a_document(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, [
            'attachment' => UploadedFile::fake()->create('payroll.pdf', 100, 'application/pdf'),
        ])->assertStatus(201)->assertJsonPath('message.type', 'file');
    }

    /** A refusal the sender can act on, not a 500. */
    public function test_an_oversized_image_is_refused_with_a_reason(): void
    {
        $c = $this->thread();

        $response = $this->send($this->ian, $c, [
            'attachment' => UploadedFile::fake()->create('huge.jpg', 12000, 'image/jpeg'),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('limit is', $response->json('message'));
        $this->assertSame(0, Message::count());
    }

    public function test_a_caption_rides_along_with_a_photo(): void
    {
        $c = $this->thread();

        $this->send($this->ian, $c, [
            'body'       => 'Taken at the Lubowa gate',
            'attachment' => UploadedFile::fake()->image('gate.jpg'),
        ])->assertStatus(201)
          ->assertJsonPath('message.body', 'Taken at the Lubowa gate')
          ->assertJsonPath('message.type', 'image');
    }

    // ── Who may fetch a file ─────────────────────────────────────────────

    public function test_a_participant_can_fetch_the_attachment(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['attachment' => UploadedFile::fake()->image('site.jpg')]);

        $this->actingAs($this->akol)
            ->get(route('chat.attachment', Message::first()))
            ->assertOk();
    }

    /** The whole reason attachments are not on a public path. */
    public function test_an_outsider_cannot_fetch_the_attachment(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['attachment' => UploadedFile::fake()->image('site.jpg')]);

        $this->actingAs($this->stranger)
            ->get(route('chat.attachment', Message::first()))
            ->assertForbidden();
    }

    public function test_a_signed_out_visitor_cannot_fetch_the_attachment(): void
    {
        $c = $this->thread();

        // Built through the service, not the endpoint: actingAs() persists for
        // the rest of the test, so sending it as Ian first would leave this
        // request signed in as Ian and prove nothing.
        app(\App\Services\ChatService::class)
            ->send($c, $this->ian, null, UploadedFile::fake()->image('site.jpg'));

        $this->get(route('chat.attachment', Message::first()))->assertRedirect();
    }

    // ── Unread ───────────────────────────────────────────────────────────

    public function test_the_recipient_has_an_unread_count(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'One']);
        $this->send($this->ian, $c, ['body' => 'Two']);

        $this->actingAs($this->akol)->get(route('chat.unread'))->assertJsonPath('unread', 2);
    }

    public function test_your_own_message_is_not_unread_to_you(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'One']);

        $this->actingAs($this->ian)->get(route('chat.unread'))->assertJsonPath('unread', 0);
    }

    public function test_opening_the_thread_clears_it(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'One']);

        $this->actingAs($this->akol)->get(route('chat.messages', $c))->assertOk();
        $this->actingAs($this->akol)->get(route('chat.unread'))->assertJsonPath('unread', 0);
    }

    /**
     * Polling must not mark anything read. A message arriving while the panel
     * sits in the background would otherwise be counted as seen by nobody.
     */
    public function test_polling_for_new_lines_does_not_mark_them_read(): void
    {
        $c = $this->thread();
        $this->actingAs($this->akol)->get(route('chat.messages', $c));

        // The timestamps are second-precision and a test runs inside one, so
        // without this the message and the read land together and the strict
        // comparison drops it. A person cannot read and receive in the same
        // instant; a test can.
        $this->travel(2)->seconds();

        $this->send($this->ian, $c, ['body' => 'Arrived while you were away']);

        $this->actingAs($this->akol)->get(route('chat.messages', $c).'?after=0')->assertOk();

        $this->actingAs($this->akol)->get(route('chat.unread'))->assertJsonPath('unread', 1);
    }

    // ── Being told ───────────────────────────────────────────────────────

    public function test_the_other_person_gets_a_notification(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'Are the payslips ready?']);

        $note = Notification::where('user_id', $this->akol->id)->where('type', 'chat')->first();

        $this->assertNotNull($note);
        $this->assertStringContainsString('Ian Kirabo', $note->body);
    }

    /** One notice per thread, refreshed - not one per line typed. */
    public function test_a_burst_of_messages_makes_one_notification(): void
    {
        $c = $this->thread();

        foreach (['One', 'Two', 'Three'] as $line) {
            $this->send($this->ian, $c, ['body' => $line]);
        }

        $this->assertSame(1, Notification::where('user_id', $this->akol->id)->where('type', 'chat')->count());
        $this->assertStringContainsString('Three',
            Notification::where('user_id', $this->akol->id)->first()->body);
    }

    public function test_a_photo_reads_as_photo_in_the_list(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['attachment' => UploadedFile::fake()->image('site.jpg')]);

        $this->assertSame('Photo', $c->refresh()->last_message_preview);
    }

    // ── Taking it back ───────────────────────────────────────────────────

    public function test_you_can_delete_your_own_message(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'Sent by mistake']);

        $this->actingAs($this->ian)
            ->delete(route('chat.message.destroy', Message::first()))
            ->assertOk();

        $this->assertSame(0, Message::count());
        $this->assertSame(1, Message::withTrashed()->count(), 'It should be soft-deleted, not erased.');
    }

    public function test_you_cannot_delete_somebody_elses(): void
    {
        $c = $this->thread();
        $this->send($this->ian, $c, ['body' => 'Mine']);

        $this->actingAs($this->akol)
            ->delete(route('chat.message.destroy', Message::first()))
            ->assertForbidden();

        $this->assertSame(1, Message::count());
    }

    // ── The panel ────────────────────────────────────────────────────────

    public function test_the_chat_button_is_on_every_signed_in_page(): void
    {
        $this->actingAs($this->ian)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('chatPanel()', false);
    }

    public function test_contacts_can_be_searched(): void
    {
        $this->actingAs($this->ian)
            ->get(route('chat.contacts', ['q' => 'Akol']))
            ->assertOk()
            ->assertJsonPath('contacts.0.name', 'Akol Deograceous');
    }

    public function test_you_are_not_in_your_own_contact_list(): void
    {
        $names = $this->actingAs($this->ian)
            ->get(route('chat.contacts'))->json('contacts.*.name');

        $this->assertNotContains('Ian Kirabo', $names);
    }
}
