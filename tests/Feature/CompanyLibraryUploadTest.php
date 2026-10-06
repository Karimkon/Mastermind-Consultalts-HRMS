<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\QualityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Company Documents: the quality manager and the auditors submit to it,
 * everybody else reads and downloads.
 *
 * Submitting is not publishing. A document sent from the library goes to a
 * named approver and runs the same Initiator -> Approver -> Publish chain as
 * anything raised in Document Control, so nothing reaches every member of staff
 * without somebody having agreed to it.
 *
 * The library route is open to every signed-in member of staff, so the gate
 * cannot live in the route middleware - it is in
 * QualityDocumentController::libraryUpload(). These tests post a valid payload
 * as each role, because hiding the button is not the same as refusing the write.
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
            'name' => 'Test ' . ($roles ? implode('+', $roles) : 'employee'),
            'email' => $email,
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole(array_merge(['employee'], $roles));

        return $user;
    }

    private function approver(): User
    {
        // Approval is the CEO's or the Admin's now, and nobody else's.
        return $this->userWithRoles(['md'], 'ceo@test.local');
    }

    private function payload(User $approver): array
    {
        return [
            'title' => 'Staff Handbook',
            'category' => 'manual',
            'file' => UploadedFile::fake()->create('handbook.pdf', 32, 'application/pdf'),
        ];
    }

    // ===== Who may submit =====

    public static function submitters(): array
    {
        // Company Documents is raised by the EHS Officer, the System Admin or
        // the CEO. The quality manager and the auditors were on this list and
        // are not any more: the quality manager runs Document Control, which
        // is a different thing from what the whole company reads.
        return [
            'EHS officer' => [['ehs-officer']],
            'system admin' => [['super-admin']],
            'CEO' => [['md']],
        ];
    }

    #[DataProvider('submitters')]
    public function test_the_library_keepers_can_submit(array $roles): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $user = $this->userWithRoles($roles, implode('', $roles) . '@test.local');

        $this->actingAs($user)
            ->post('/quality/library', $this->payload($approver))
            ->assertRedirect(route('quality.documents.library'))
            ->assertSessionHasNoErrors();

        $doc = QualityDocument::where('title', 'Staff Handbook')->first();

        $this->assertNotNull($doc, 'Nothing was created.');
        $this->assertSame('pending_approval', $doc->status, 'The submission did not go for approval.');
        $this->assertSame($user->id, $doc->initiator_id);
        $this->assertNotNull($doc->currentFile());
        Storage::disk('local')->assertExists($doc->currentFile()->path);
    }

    public static function readers(): array
    {
        return [
            'ordinary employee' => [[]],
            'manager' => [['manager']],
            'account manager' => [['account-manager']],
            'quality manager' => [['quality-manager']],
            'auditor' => [['auditor']],
            'hr admin' => [['hr-admin']],
        ];
    }

    #[DataProvider('readers')]
    public function test_everybody_else_is_refused_even_with_a_valid_payload(array $roles): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $email = ($roles ? implode('', $roles) : 'plain') . '-reader@test.local';
        $user = $this->userWithRoles($roles, $email);

        $this->actingAs($user)
            ->post('/quality/library', $this->payload($approver))
            ->assertForbidden();

        $this->assertSame(0, QualityDocument::count(), 'A reader created a document.');
    }

    public function test_a_client_cannot_reach_the_library_at_all(): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $client = $this->userWithRoles(['client'], 'client@test.local');

        $this->actingAs($client)->get('/quality/library')->assertForbidden();
        $this->actingAs($client)->post('/quality/library', $this->payload($approver))->assertForbidden();
    }

    // ===== Nothing reaches the company unapproved =====

    public function test_a_submission_does_not_appear_in_the_library(): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $keeper = $this->userWithRoles(['ehs-officer'], 'auditor@test.local');

        $this->actingAs($keeper)->post('/quality/library', $this->payload($approver));

        // Not published, so an ordinary employee cannot see or fetch it.
        $reader = $this->userWithRoles([], 'reader@test.local');
        $this->actingAs($reader)->get('/quality/library')
            ->assertOk()
            ->assertDontSee('Staff Handbook', false);

        $doc = QualityDocument::first();
        $this->actingAs($reader)
            ->get("/quality/documents/{$doc->id}/files/{$doc->currentFile()->id}/download")
            ->assertForbidden();
    }

    /**
     * Nobody nominates an approver any more, and the submitter cannot approve.
     *
     * The form used to ask who should approve the document, and nothing
     * stopped the person submitting it from naming themselves. Approval now
     * belongs to the CEO and the Admin by virtue of the office, so there is
     * nothing to nominate and nothing to nominate yourself for.
     */
    public function test_the_submitter_cannot_approve_their_own_document(): void
    {
        Storage::fake('local');
        $keeper = $this->userWithRoles(['ehs-officer'], 'ehs-self@test.local');

        // Submitting without naming anybody is now the normal path.
        $this->actingAs($keeper)
            ->post('/quality/library', [
                'title' => 'Staff Handbook',
                'category' => 'manual',
                'file' => UploadedFile::fake()->create('h.pdf', 8, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $doc = QualityDocument::firstOrFail();
        $this->assertSame('pending_approval', $doc->status);
        $this->assertNull($doc->approver_id, 'Nobody should have been nominated.');

        // And the person who raised it cannot wave it through.
        $this->actingAs($keeper)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame('pending_approval', $doc->fresh()->status);
    }

    public function test_the_approver_is_told(): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $keeper = $this->userWithRoles(['ehs-officer'], 'auditor2@test.local');

        $this->actingAs($keeper)->post('/quality/library', $this->payload($approver));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $approver->id,
            'type' => 'quality_alert',
        ]);
        $this->assertStringContainsString(
            'Staff Handbook',
            Notification::where('user_id', $approver->id)->first()->title
        );
    }

    /** The whole point: approved and published, then everyone has it. */
    public function test_once_approved_and_published_the_company_can_read_it(): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $keeper = $this->userWithRoles(['ehs-officer'], 'qm2@test.local');

        $this->actingAs($keeper)->post('/quality/library', $this->payload($approver));
        $doc = QualityDocument::first();

        // The named approver approves.
        $this->actingAs($approver)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => 'approve'])
            ->assertRedirect();
        $this->assertSame('approved', $doc->fresh()->status);

        // Still not in the library until somebody publishes it.
        $reader = $this->userWithRoles([], 'reader2@test.local');
        $this->actingAs($reader)->get('/quality/library')->assertDontSee('Staff Handbook', false);

        // A manager publishes.
        $this->actingAs($keeper)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => 'publish'])
            ->assertRedirect();
        $this->assertSame('published', $doc->fresh()->status);

        $this->actingAs($reader)->get('/quality/library')
            ->assertOk()
            ->assertSee('Staff Handbook', false)
            ->assertSee('data-download-progress', false);

        $this->actingAs($reader)
            ->get("/quality/documents/{$doc->id}/files/{$doc->currentFile()->id}/download")
            ->assertOk()
            ->assertDownload('handbook.pdf');
    }

    /**
     * The EHS Officer publishes, but never something unapproved.
     *
     * Publishing was hidden from them entirely, which is what the instruction
     * was about. Letting them publish does not let them publish their own
     * work unchecked: they cannot approve, so anything they put out has
     * already been agreed by the CEO or the Admin.
     */
    public function test_the_ehs_officer_publishes_only_what_has_been_approved(): void
    {
        Storage::fake('local');
        $ceo = $this->approver();
        $ehs = $this->userWithRoles(['ehs-officer'], 'ehs-pub@test.local');

        $this->actingAs($ehs)->post('/quality/library', $this->payload($ceo));
        $doc = QualityDocument::first();

        // Not yet approved — publishing is refused.
        $this->actingAs($ehs)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => 'publish'])
            ->assertForbidden();
        $this->assertSame('pending_approval', $doc->fresh()->status);

        // The CEO approves, and now the EHS Officer may publish it.
        $this->actingAs($ceo)->post("/quality/documents/{$doc->id}/transition", ['action' => 'approve']);

        $this->actingAs($ehs)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => 'publish'])
            ->assertRedirect();

        $this->assertSame('published', $doc->fresh()->status);
    }

    // ===== What each role is shown =====

    public function test_the_keepers_are_shown_the_button_and_the_uploader(): void
    {
        $response = $this->actingAs($this->userWithRoles(['ehs-officer'], 'a@test.local'))
            ->get('/quality/library')
            ->assertOk();

        $response->assertSee('Add a document', false);
        $response->assertSee('Send for approval', false);
        $response->assertSee('data-file-drop', false);
        $response->assertSee('Any file format', false);
        // The live progress wiring, from <x-transfer-progress>.
        $response->assertSee('data-upload-progress', false);
        $response->assertSee('data-pp-bar', false);
    }

    /**
     * The hidden file field must NOT carry the native `required` attribute.
     *
     * Chrome refuses to submit a form containing a required control it cannot
     * focus - it aborts with "An invalid form control ... is not focusable" and
     * does nothing at all: no post, no error, no clue. The field is visually
     * hidden so the drop zone can be the control, so the two cannot coexist.
     *
     * This is why the uploader appeared to do nothing while every POST test
     * here passed: the tests post straight to the route and never go through
     * the browser's form validation at all.
     */
    public function test_the_hidden_file_field_carries_no_native_required(): void
    {
        $html = $this->actingAs($this->userWithRoles(['ehs-officer'], 'markup@test.local'))
            ->get('/quality/library')
            ->assertOk()
            ->getContent();

        $start = strpos($html, 'data-file-drop-input');
        $this->assertNotFalse($start, 'The drop zone input is missing from the page.');

        // The whole <input ...> tag the drop zone uses.
        $tagStart = strrpos(substr($html, 0, $start), '<input');
        $tag = substr($html, $tagStart, strpos($html, '>', $start) - $tagStart + 1);

        $this->assertStringContainsString('sr-only', $tag, 'Expected the hidden input.');
        $this->assertDoesNotMatchRegularExpression(
            '/\srequired[\s>=]/',
            $tag,
            "The hidden file input has the native `required` attribute. Chrome will "
            . "silently refuse to submit the form. Use data-file-drop-required instead:\n" . $tag
        );
        $this->assertStringContainsString('data-file-drop-required', $tag,
            'The requirement must still be marked, for the script and the server to enforce.');
    }

    public function test_a_submitter_sees_what_is_waiting_on_somebody_else(): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $keeper = $this->userWithRoles(['ehs-officer'], 'auditor4@test.local');

        $this->actingAs($keeper)->post('/quality/library', $this->payload($approver));

        $this->actingAs($keeper)->get('/quality/library')
            ->assertOk()
            ->assertSee('waiting on approval', false)
            ->assertSee('Staff Handbook', false);
    }

    public function test_an_ordinary_employee_is_shown_no_way_to_submit(): void
    {
        $response = $this->actingAs($this->userWithRoles([], 'plain@test.local'))
            ->get('/quality/library')
            ->assertOk();

        // Assert on markup only the uploader produces. The data- hooks are no
        // good here: <x-transfer-progress> ships its selectors in a script on
        // every page, so "data-file-drop" matches even with no drop zone on it.
        $response->assertDontSee('Add a document', false);
        $response->assertDontSee('id="library-upload"', false);
        $response->assertDontSee('Any file format', false);
        $response->assertDontSee('name="file"', false);
        $response->assertDontSee('enctype="multipart/form-data"', false);
    }

    public function test_the_submission_refuses_nothing_but_oversize(): void
    {
        Storage::fake('local');
        $approver = $this->approver();
        $keeper = $this->userWithRoles(['ehs-officer'], 'auditor5@test.local');

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
