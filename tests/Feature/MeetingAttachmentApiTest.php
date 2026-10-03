<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingFile;
use App\Models\MeetingParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Meeting papers over the API, for the mobile app.
 *
 * The gate is written out again in the API controller rather than shared with
 * the web one: the two run under different guards, and a single helper that
 * quietly assumed a session is exactly the kind of thing that stops checking.
 * So it is tested again here too, rather than assumed from the web tests.
 *
 * The file LIST is withheld as well as the bytes. Knowing that a colleague's
 * appraisal pack exists, and what it is called, is worth withholding on its own.
 */
class MeetingAttachmentApiTest extends TestCase
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
            'emp_number' => 'API' . $user->id,
            'first_name' => $name,
            'last_name' => 'Tester',
            'status' => 'active',
            'hire_date' => '2024-01-01',
        ]);

        return $user->fresh();
    }

    private function meetingWithFile(User $organiser): array
    {
        $meeting = Meeting::create([
            'title' => 'Quarterly review',
            'organizer_id' => $organiser->employee->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        $file = MeetingFile::create([
            'meeting_id' => $meeting->id,
            'path' => 'meetings/' . $meeting->id . '/pack.pdf',
            'original_name' => 'board-pack.pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => $organiser->id,
        ]);

        Storage::disk('local')->put($file->path, 'the board pack');

        return [$meeting, $file];
    }

    public function test_an_invited_participant_sees_the_files_in_the_meeting(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'apiorg@test.local');
        $invitee = $this->staff('Invitee', 'apiinv@test.local');
        [$meeting, $file] = $this->meetingWithFile($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $invitee->employee->id, 'rsvp' => 'accepted',
        ]);

        Sanctum::actingAs($invitee);

        $response = $this->getJson("/api/meetings/{$meeting->id}")->assertOk();

        $response->assertJsonPath('data.files.0.name', 'board-pack.pdf');
        $response->assertJsonPath('data.files.0.id', $file->id);
        $response->assertJsonStructure([
            'data' => ['files' => [['id', 'name', 'mime', 'size', 'readable_size', 'download_url']]],
        ]);
        $this->assertSame('2 KB', $response->json('data.files.0.readable_size'));
    }

    /** The list itself is withheld, not just the download. */
    public function test_somebody_not_invited_sees_no_files_at_all(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'apiorg2@test.local');
        $outsider = $this->staff('Outsider', 'apiout2@test.local');
        [$meeting] = $this->meetingWithFile($organiser);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/meetings/{$meeting->id}")
            ->assertOk()
            ->assertJsonPath('data.files', [])
            ->assertJsonMissing(['name' => 'board-pack.pdf']);
    }

    public function test_an_invited_participant_can_download_through_the_api(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'apiorg3@test.local');
        $invitee = $this->staff('Invitee', 'apiinv3@test.local');
        [$meeting, $file] = $this->meetingWithFile($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $invitee->employee->id, 'rsvp' => 'accepted',
        ]);

        Sanctum::actingAs($invitee);

        $this->get("/api/meetings/{$meeting->id}/files/{$file->id}")
            ->assertOk()
            ->assertDownload('board-pack.pdf');
    }

    public function test_an_outsider_is_refused_the_download(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'apiorg4@test.local');
        $outsider = $this->staff('Outsider', 'apiout4@test.local');
        [$meeting, $file] = $this->meetingWithFile($organiser);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/meetings/{$meeting->id}/files/{$file->id}")->assertForbidden();
    }

    public function test_a_file_cannot_be_fetched_through_another_meeting(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'apiorg5@test.local');
        $other = $this->staff('Other', 'apioth5@test.local');
        [$meeting, $file] = $this->meetingWithFile($organiser);

        $theirs = Meeting::create([
            'title' => 'Their own meeting',
            'organizer_id' => $other->employee->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/meetings/{$theirs->id}/files/{$file->id}")->assertNotFound();
    }

    /** The list carries a count, so the app can show a paperclip without a second call. */
    public function test_the_meeting_list_reports_how_many_files_there_are(): void
    {
        Storage::fake('local');
        $organiser = $this->staff('Organiser', 'apiorg6@test.local');
        [$meeting] = $this->meetingWithFile($organiser);
        MeetingParticipant::create([
            'meeting_id' => $meeting->id, 'employee_id' => $organiser->employee->id, 'rsvp' => 'accepted',
        ]);

        Sanctum::actingAs($organiser);

        // The endpoint paginates, so the rows sit at data.data, not data.
        $rows = $this->getJson('/api/meetings')->assertOk()->json('data.data');

        $this->assertNotEmpty($rows, 'The meetings list came back empty.');
        $this->assertSame(1, collect($rows)->firstWhere('id', $meeting->id)['file_count'] ?? null);
    }
}
