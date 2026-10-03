<?php

namespace Tests\Feature;

use App\Models\QualityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Company Documents: the quality manager and the auditors maintain it,
 * everybody else reads and downloads.
 *
 * The library is open to every member of staff, so the gate cannot live in the
 * route middleware - it is in QualityDocumentController::libraryUpload(). These
 * tests post to that endpoint directly with a valid payload as each role,
 * because hiding the button is not the same as refusing the write.
 *
 * Uploading here publishes straight to the whole company, skipping the
 * Initiator -> Editor -> Approver chain. That is deliberate, and the bypass is
 * written into the document's timeline rather than left silent - the last test
 * holds that record in place.
 */
class CompanyLibraryUploadTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(array $roles, string $email): User
    {
        Role::findOrCreate('employee', 'web');
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::create([
            'name' => 'Test ' . implode('+', $roles),
            'email' => $email,
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole(array_merge(['employee'], $roles));

        return $user;
    }

    private function payload(): array
    {
        return [
            'title' => 'Staff Handbook',
            'category' => 'manual',
            'file' => UploadedFile::fake()->create('handbook.pdf', 32, 'application/pdf'),
        ];
    }

    // ===== Who may add to the library =====

    public static function uploaders(): array
    {
        return [
            'quality manager' => [['quality-manager']],
            'auditor' => [['auditor']],
            'hr admin' => [['hr-admin']],
            'super admin' => [['super-admin']],
        ];
    }

    #[DataProvider('uploaders')]
    public function test_the_library_keepers_can_upload(array $roles): void
    {
        Storage::fake('local');
        $user = $this->userWithRoles($roles, implode('', $roles) . '@test.local');

        $this->actingAs($user)
            ->post('/quality/library', $this->payload())
            ->assertRedirect(route('quality.documents.library'))
            ->assertSessionHasNoErrors();

        $doc = QualityDocument::first();

        $this->assertNotNull($doc, 'Nothing was created.');
        $this->assertSame('published', $doc->status, 'The upload did not reach the library.');
        $this->assertNotNull($doc->currentFile());
        Storage::disk('local')->assertExists($doc->currentFile()->path);
    }

    public static function readers(): array
    {
        return [
            'ordinary employee' => [[]],
            'manager' => [['manager']],
            'account manager' => [['account-manager']],
        ];
    }

    #[DataProvider('readers')]
    public function test_everybody_else_is_refused_even_with_a_valid_payload(array $roles): void
    {
        Storage::fake('local');
        $email = ($roles ? implode('', $roles) : 'plain') . '-reader@test.local';
        $user = $this->userWithRoles($roles, $email);

        $this->actingAs($user)
            ->post('/quality/library', $this->payload())
            ->assertForbidden();

        $this->assertSame(0, QualityDocument::count(), 'A reader created a document.');
    }

    public function test_a_client_cannot_reach_the_library_at_all(): void
    {
        Storage::fake('local');
        $client = $this->userWithRoles(['client'], 'client@test.local');

        $this->actingAs($client)->get('/quality/library')->assertForbidden();
        $this->actingAs($client)->post('/quality/library', $this->payload())->assertForbidden();
    }

    // ===== What each role is shown =====

    public function test_the_keepers_are_shown_the_attach_button_and_the_uploader(): void
    {
        $response = $this->actingAs($this->userWithRoles(['auditor'], 'a@test.local'))
            ->get('/quality/library')
            ->assertOk();

        $response->assertSee('Attach a document', false);
        $response->assertSee('data-file-drop', false);
        $response->assertSee('Any file format', false);
        // The live progress wiring, from <x-transfer-progress>.
        $response->assertSee('data-upload-progress', false);
        $response->assertSee('data-pp-bar', false);
    }

    public function test_an_ordinary_employee_is_shown_no_way_to_upload(): void
    {
        $response = $this->actingAs($this->userWithRoles([], 'plain@test.local'))
            ->get('/quality/library')
            ->assertOk();

        // Assert on markup only the uploader produces. The data- hooks are no
        // good here: <x-transfer-progress> ships its selectors in a script on
        // every page, so "data-file-drop" matches even with no drop zone on it.
        $response->assertDontSee('Attach a document', false);
        $response->assertDontSee('id="library-upload"', false);
        $response->assertDontSee('Any file format', false);
        // Not the upload URL: it is the same path as the library itself, so it
        // is on the page either way as the sidebar link and the filter links.
        $response->assertDontSee('name="file"', false);
        $response->assertDontSee('enctype="multipart/form-data"', false);
    }

    /** Reading and downloading stay open to everyone. */
    public function test_an_ordinary_employee_can_still_download(): void
    {
        Storage::fake('local');

        $keeper = $this->userWithRoles(['quality-manager'], 'qm@test.local');
        $this->actingAs($keeper)->post('/quality/library', $this->payload());

        $doc = QualityDocument::first();
        $file = $doc->currentFile();

        $response = $this->actingAs($this->userWithRoles([], 'reader@test.local'))
            ->get('/quality/library')
            ->assertOk();
        $response->assertSee('Staff Handbook', false);
        $response->assertSee('data-download-progress', false);

        $this->actingAs($this->userWithRoles([], 'reader2@test.local'))
            ->get("/quality/documents/{$doc->id}/files/{$file->id}/download")
            ->assertOk()
            ->assertDownload('handbook.pdf');
    }

    // ===== The bypass is recorded =====

    public function test_skipping_the_approval_chain_is_written_into_the_timeline(): void
    {
        Storage::fake('local');
        $keeper = $this->userWithRoles(['auditor'], 'auditor2@test.local');

        $this->actingAs($keeper)->post('/quality/library', $this->payload());

        $actions = QualityDocument::first()->events->pluck('action')->all();

        $this->assertContains('published', $actions);
        $this->assertContains('created', $actions);
        $this->assertStringContainsString(
            'without the editor and approver',
            QualityDocument::first()->events->firstWhere('action', 'published')->note,
            'The approval bypass was not recorded on the document.'
        );
    }

    public function test_the_upload_still_refuses_nothing_but_oversize(): void
    {
        Storage::fake('local');
        $keeper = $this->userWithRoles(['auditor'], 'auditor3@test.local');

        // An unusual format is fine - a controlled document is whatever the
        // company issues.
        $this->actingAs($keeper)->post('/quality/library', [
            'title' => 'Site Plan',
            'category' => 'record',
            'file' => UploadedFile::fake()->create('plan.dwg', 32, 'application/acad'),
        ])->assertSessionHasNoErrors();

        // Size is the one limit.
        $this->actingAs($keeper)->post('/quality/library', [
            'title' => 'Huge',
            'category' => 'record',
            'file' => UploadedFile::fake()->create('huge.mp4', QualityDocument::maxUploadKb() + 1024, 'video/mp4'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(1, QualityDocument::count());
    }
}
