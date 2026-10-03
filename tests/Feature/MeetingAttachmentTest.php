<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingFile;
use App\Models\MeetingParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Papers for a meeting: the organizer attaches them, the people invited read
 * them, and nobody else can.
 *
 * An agenda is of limited use without the document it is about, so these were
 * going round by email and the version in the room depended on who had read
 * which message.
 *
 * The files sit on the PRIVATE disk and are served by a controller. That is the
 * whole point of the tests below: a meeting pack can be somebody's appraisal, a
 * disciplinary note or a board paper, and 1,281 accounts must not be able to
 * fetch one by walking ids.
 */
class MeetingAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $name, string $email, array $roles = []): User
    {
        Role::findOrCreate('employee', 'web');
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => bcrypt('secret')]);
        $user->assignRole(array_merge(['employee'], $roles));

        Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'MTG' . $user->id,
            'first_name' => $name,
            'last_name' => 'Tester',
            'status' => 'active',
            'hire_date' => '2024-01-01',
        ]);

        return $user->fresh();
    }

    private function meetingBy(User $organiser): Meeting
    {
        return Meeting::create([
            'title' => 'Quarterly review',
            'organizer_id' => $organiser->employee->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);
    }

    private function attach(Meeting $meeting, User $by): MeetingFile
    {
        return MeetingFile::create([
            'meeting_id' => $meeting->id,
            'path' => 'meetings/' . $meeting->id . '/pack.pdf',
            'original_name' => 'board-pack.pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => $by->id,
        ]);
    }

    // ===== Attaching =====

    public function test_the_organiser_attaches_files_when_scheduling(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org@test.local');
        $invitee = $this->staff('Invitee', 'inv@test.local');

        $this->actingAs($organiser)->post('/meetings', [
            'title' => 'Quarterly review',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'participants' => [$invitee->employee->id],
            'files' => [
                UploadedFile::fake()->create('agenda.pdf', 64, 'application/pdf'),
                UploadedFile::fake()->create('figures.xlsx', 32,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ],
        ])->assertRedirect();

        $meeting = Meeting::first();
        $this->assertCount(2, $meeting->files, 'The attachments were not saved.');

        foreach ($meeting->files as $f) {
            Storage::disk('local')->assertExists($f->path);
            // Private disk, not public: a meeting pack is not a public URL.
            $this->assertStringStartsWith('meetings/' . $meeting->id, $f->path);
        }
    }

    public function test_a_meeting_can_be_scheduled_with_no_files_at_all(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org2@test.local');

        $this->actingAs($organiser)->post('/meetings', [
            'title' => 'Standup',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertSame(0, Meeting::first()->files()->count());
    }

    public function test_the_organiser_can_attach_after_the_fact(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org3@test.local');
        $meeting = $this->meetingBy($organiser);

        $this->actingAs($organiser)
            ->post("/meetings/{$meeting->id}/files", [
                'files' => [UploadedFile::fake()->create('late-paper.pdf', 16, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertSame(1, $meeting->fresh()->files()->count());
    }

    public function test_an_invitee_cannot_attach_papers(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org4@test.local');
        $invitee = $this->staff('Invitee', 'inv4@test.local');
        $meeting = $this->meetingBy($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $invitee->employee->id, 'rsvp' => 'pending',
        ]);

        $this->actingAs($invitee)
            ->post("/meetings/{$meeting->id}/files", [
                'files' => [UploadedFile::fake()->create('sneaky.pdf', 16, 'application/pdf')],
            ])
            ->assertForbidden();

        $this->assertSame(0, $meeting->fresh()->files()->count());
    }

    // ===== Reading: the part that must not leak =====

    public function test_an_invited_participant_can_download(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org5@test.local');
        $invitee = $this->staff('Invitee', 'inv5@test.local');
        $meeting = $this->meetingBy($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $invitee->employee->id, 'rsvp' => 'accepted',
        ]);

        $file = $this->attach($meeting, $organiser);
        Storage::disk('local')->put($file->path, 'the board pack');

        $this->actingAs($invitee)
            ->get("/meetings/{$meeting->id}/files/{$file->id}")
            ->assertOk()
            ->assertDownload('board-pack.pdf');
    }

    public function test_the_organiser_can_download(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org6@test.local');
        $meeting = $this->meetingBy($organiser);
        $file = $this->attach($meeting, $organiser);
        Storage::disk('local')->put($file->path, 'the board pack');

        $this->actingAs($organiser)
            ->get("/meetings/{$meeting->id}/files/{$file->id}")
            ->assertOk();
    }

    /** The one that matters. */
    public function test_somebody_not_invited_is_refused(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org7@test.local');
        $outsider = $this->staff('Outsider', 'out7@test.local');
        $meeting = $this->meetingBy($organiser);
        $file = $this->attach($meeting, $organiser);
        Storage::disk('local')->put($file->path, 'the board pack');

        $this->actingAs($outsider)
            ->get("/meetings/{$meeting->id}/files/{$file->id}")
            ->assertForbidden();
    }

    public function test_a_file_cannot_be_fetched_through_another_meeting(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org8@test.local');
        $other = $this->staff('Other', 'oth8@test.local');

        $meeting = $this->meetingBy($organiser);
        $file = $this->attach($meeting, $organiser);
        Storage::disk('local')->put($file->path, 'the board pack');

        // A meeting the caller legitimately owns, used as the route key.
        $theirs = $this->meetingBy($other);

        $this->actingAs($other)
            ->get("/meetings/{$theirs->id}/files/{$file->id}")
            ->assertNotFound();
    }

    public function test_an_administrator_can_reach_the_pack(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org9@test.local');
        $admin = $this->staff('Admin', 'adm9@test.local', ['hr-admin']);
        $meeting = $this->meetingBy($organiser);
        $file = $this->attach($meeting, $organiser);
        Storage::disk('local')->put($file->path, 'the board pack');

        $this->actingAs($admin)
            ->get("/meetings/{$meeting->id}/files/{$file->id}")
            ->assertOk();
    }

    // ===== Removing =====

    public function test_the_organiser_removes_a_file_and_the_bytes_go_too(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org10@test.local');
        $meeting = $this->meetingBy($organiser);
        $file = $this->attach($meeting, $organiser);
        Storage::disk('local')->put($file->path, 'the board pack');

        $this->actingAs($organiser)
            ->delete("/meetings/{$meeting->id}/files/{$file->id}")
            ->assertRedirect();

        $this->assertNull(MeetingFile::find($file->id));
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_an_invitee_cannot_remove_a_file(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org11@test.local');
        $invitee = $this->staff('Invitee', 'inv11@test.local');
        $meeting = $this->meetingBy($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $invitee->employee->id, 'rsvp' => 'accepted',
        ]);
        $file = $this->attach($meeting, $organiser);

        $this->actingAs($invitee)
            ->delete("/meetings/{$meeting->id}/files/{$file->id}")
            ->assertForbidden();

        $this->assertNotNull(MeetingFile::find($file->id));
    }

    // ===== What each person is shown =====

    public function test_the_meeting_page_shows_the_papers_to_those_invited(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'org12@test.local');
        $invitee = $this->staff('Invitee', 'inv12@test.local');
        $meeting = $this->meetingBy($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $invitee->employee->id, 'rsvp' => 'accepted',
        ]);
        $this->attach($meeting, $organiser);

        $invited = $this->actingAs($invitee)->get("/meetings/{$meeting->id}")->assertOk();
        $invited->assertSee('board-pack.pdf', false);
        $invited->assertSee('data-download-progress', false);
        // Reading, not editing.
        $invited->assertDontSee('Attach documents', false);

        $own = $this->actingAs($organiser)->get("/meetings/{$meeting->id}")->assertOk();
        $own->assertSee('board-pack.pdf', false);
        $own->assertSee('Attach documents', false);
    }
}
