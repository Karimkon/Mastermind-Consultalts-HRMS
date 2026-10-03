<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\QualityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One real document, the whole way through Company Documents.
 *
 * Everything else in this suite uses UploadedFile::fake(), which creates a file
 * of the right size full of nothing. This one posts an actual PDF off disk -
 * tests/fixtures/staff-handbook.pdf, a real 1-page document - and checks the
 * bytes that come back out are the same bytes that went in. A round trip that
 * loses or truncates content would pass every other test in this file.
 */
class LibraryRealDocumentJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = __DIR__ . '/../fixtures/staff-handbook.pdf';

    private function user(array $roles, string $email, string $name): User
    {
        Role::findOrCreate('employee', 'web');
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
        }

        $u = User::create(['name' => $name, 'email' => $email, 'password' => bcrypt('secret')]);
        $u->assignRole(array_merge(['employee'], $roles));

        return $u;
    }

    /** The real file, handed over the way a browser hands one over. */
    private function realUpload(): UploadedFile
    {
        return new UploadedFile(
            self::FIXTURE,
            'staff-handbook.pdf',
            'application/pdf',
            null,
            true // test mode: skip the is_uploaded_file() check
        );
    }

    public function test_a_real_document_goes_from_the_auditor_to_every_employee(): void
    {
        Storage::fake('local');

        $this->assertFileExists(self::FIXTURE, 'The PDF fixture is missing.');
        $original = file_get_contents(self::FIXTURE);
        $originalHash = hash('sha256', $original);
        $this->assertStringStartsWith('%PDF-', $original, 'The fixture is not a PDF.');

        $auditor = $this->user(['auditor'], 'julius@test.local', 'Mbogga Julius');
        $approver = $this->user(['hr-admin'], 'ian@test.local', 'Ian Kirabo');
        $manager = $this->user(['quality-manager'], 'qm@test.local', 'Quality Manager');
        $employee = $this->user([], 'staff@test.local', 'Ordinary Staff');

        // --- 1. the auditor submits it ---------------------------------
        $this->actingAs($auditor)
            ->post('/quality/library', [
                'title' => 'Staff Handbook',
                'category' => 'manual',
                'description' => 'Issued to all staff.',
                'approver_id' => $approver->id,
                'file' => $this->realUpload(),
            ])
            ->assertRedirect(route('quality.documents.library'))
            ->assertSessionHasNoErrors();

        $doc = QualityDocument::firstWhere('title', 'Staff Handbook');
        $this->assertNotNull($doc, 'The submission created nothing.');
        $this->assertSame('pending_approval', $doc->status);

        $file = $doc->currentFile();
        $this->assertSame('staff-handbook.pdf', $file->original_name);
        $this->assertSame(strlen($original), (int) $file->size, 'The stored size does not match the file.');

        // The bytes on the disk are the bytes from the fixture.
        $this->assertSame(
            $originalHash,
            hash('sha256', Storage::disk('local')->get($file->path)),
            'The stored file is not byte-identical to the one that was uploaded.'
        );

        // --- 2. nobody else can see it yet -----------------------------
        $this->actingAs($employee)->get('/quality/library')
            ->assertOk()
            ->assertDontSee('Staff Handbook', false);
        $this->actingAs($employee)
            ->get("/quality/documents/{$doc->id}/files/{$file->id}/download")
            ->assertForbidden();

        // --- 3. the approver was told, and approves --------------------
        $this->assertDatabaseHas('notifications', [
            'user_id' => $approver->id,
            'type' => 'quality_alert',
        ]);
        $this->assertStringContainsString(
            'Staff Handbook',
            Notification::where('user_id', $approver->id)->first()->title
        );

        $this->actingAs($approver)
            ->post("/quality/documents/{$doc->id}/transition", [
                'action' => 'approve',
                'note' => 'Checked against the 2026 policy set.',
            ])
            ->assertRedirect();
        $this->assertSame('approved', $doc->fresh()->status);

        // Approved is still not published.
        $this->actingAs($employee)->get('/quality/library')->assertDontSee('Staff Handbook', false);

        // --- 4. a manager publishes it ---------------------------------
        $this->actingAs($manager)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => 'publish'])
            ->assertRedirect();
        $this->assertSame('published', $doc->fresh()->status);

        // --- 5. an ordinary employee reads and downloads it ------------
        $this->actingAs($employee)->get('/quality/library')
            ->assertOk()
            ->assertSee('Staff Handbook', false)
            ->assertSee('data-filename="staff-handbook.pdf"', false);

        $download = $this->actingAs($employee)
            ->get("/quality/documents/{$doc->id}/files/{$file->id}/download")
            ->assertOk()
            ->assertDownload('staff-handbook.pdf');

        // --- 6. what they got is what the auditor sent -----------------
        $returned = $download->streamedContent();

        $this->assertSame(
            $originalHash,
            hash('sha256', $returned),
            'The downloaded document differs from the one that was uploaded.'
        );
        $this->assertStringStartsWith('%PDF-', $returned, 'The download is not a PDF.');
        $this->assertStringContainsString('Mastermind Consult Ltd', $returned);
        $this->assertStringContainsString('%%EOF', $returned, 'The download is truncated.');

        // --- 7. the trail says how it got there ------------------------
        $trail = $doc->fresh()->events->pluck('action')->all();
        foreach (['created', 'submitted_for_approval', 'approved', 'published'] as $step) {
            $this->assertContains($step, $trail, "The timeline is missing '{$step}'.");
        }
    }
}
