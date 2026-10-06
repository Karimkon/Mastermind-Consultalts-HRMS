<?php

namespace Tests\Feature;

use App\Models\QualityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The rules Company Documents now runs under.
 *
 * Three offices carry it. The EHS Officer, the System Admin and the CEO raise
 * documents; the CEO and the Admin approve, and either alone is enough; those
 * two plus the EHS Officer publish. Nobody nominates an approver any more,
 * which is what let a submitter approve their own document.
 *
 * "CEO" is the md role and "System Admin" is super-admin. The quality manager
 * still runs Document Control — a separate thing from what the whole company
 * reads — and is deliberately absent from all of this.
 */
class CompanyDocumentControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One user per address. The helpers below are called more than once in a
     * single test — the CEO both approves and publishes — so creating a fresh
     * row each time collided on the unique email.
     */
    private function user(array $roles, string $email): User
    {
        foreach (array_merge($roles, ['employee']) as $r) Role::findOrCreate($r, 'web');

        $existing = User::where('email', $email)->first();
        if ($existing) return $existing;

        $u = User::create(['name' => ucfirst($roles[0] ?? 'Staff'), 'email' => $email, 'password' => bcrypt('x')]);
        if ($roles) $u->assignRole($roles);

        return $u->fresh();
    }

    private function ehs(): User   { return $this->user(['ehs-officer'], 'ehs@test.local'); }
    private function ceo(): User   { return $this->user(['md'], 'ceo@test.local'); }
    private function admin(): User { return $this->user(['super-admin'], 'admin@test.local'); }

    private function submit(User $by, string $title = 'Staff Handbook'): QualityDocument
    {
        $this->actingAs($by)->post('/quality/library', [
            'title' => $title,
            'category' => 'policy',
            'file' => UploadedFile::fake()->create('doc.pdf', 8, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return QualityDocument::where('title', $title)->firstOrFail();
    }

    private function move(User $by, QualityDocument $doc, string $action)
    {
        return $this->actingAs($by)
            ->post("/quality/documents/{$doc->id}/transition", ['action' => $action]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ===== 2. either office can approve =====

    public function test_the_ceo_can_approve_on_their_own(): void
    {
        $doc = $this->submit($this->ehs());

        $this->move($this->ceo(), $doc, 'approve')->assertRedirect();

        $this->assertSame('approved', $doc->fresh()->status);
    }

    public function test_the_admin_can_approve_on_their_own(): void
    {
        $doc = $this->submit($this->ehs());

        $this->move($this->admin(), $doc, 'approve')->assertRedirect();

        $this->assertSame('approved', $doc->fresh()->status);
    }

    public function test_nobody_else_can_approve(): void
    {
        $doc = $this->submit($this->ehs());

        foreach (['quality-manager', 'hr-admin', 'auditor', 'account-manager'] as $i => $role) {
            $this->move($this->user([$role], "no{$i}@test.local"), $doc, 'approve')->assertForbidden();
        }

        $this->assertSame('pending_approval', $doc->fresh()->status);
    }

    public function test_both_offices_are_told_when_something_needs_approving(): void
    {
        $ceo = $this->ceo();
        $admin = $this->admin();

        $doc = $this->submit($this->ehs());

        foreach ([$ceo, $admin] as $office) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $office->id,
                'title' => "Document to approve: {$doc->title}",
            ]);
        }
    }

    // ===== 3. recall and resubmit =====

    public function test_the_initiator_can_recall_a_document_nobody_has_approved(): void
    {
        $ehs = $this->ehs();
        $doc = $this->submit($ehs);

        $this->move($ehs, $doc, 'recall')->assertRedirect();

        $this->assertSame('draft', $doc->fresh()->status);
    }

    public function test_a_recalled_document_can_be_resubmitted(): void
    {
        $ehs = $this->ehs();
        $doc = $this->submit($ehs);

        $this->move($ehs, $doc, 'recall');
        $this->move($ehs, $doc, 'submit_approval')->assertRedirect();

        $this->assertSame('pending_approval', $doc->fresh()->status);
    }

    public function test_somebody_elses_document_cannot_be_recalled(): void
    {
        $doc = $this->submit($this->ehs());
        $other = $this->user(['quality-manager'], 'qm@test.local');

        $this->move($other, $doc, 'recall')->assertForbidden();

        $this->assertSame('pending_approval', $doc->fresh()->status);
    }

    public function test_an_approved_document_can_no_longer_be_recalled(): void
    {
        $ehs = $this->ehs();
        $doc = $this->submit($ehs);
        $this->move($this->ceo(), $doc, 'approve');

        // Once somebody has put their name to it, taking it back is not the
        // initiator's to do.
        $this->move($ehs, $doc, 'recall')->assertForbidden();

        $this->assertSame('approved', $doc->fresh()->status);
    }

    // ===== 4. publishing =====

    public function test_the_three_offices_can_publish_an_approved_document(): void
    {
        foreach ([[$this->ehs(), 'ehs'], [$this->ceo(), 'ceo'], [$this->admin(), 'admin']] as [$who, $label]) {
            $doc = $this->submit($this->ehs(), "Handbook {$label}");
            $this->move($this->ceo(), $doc, 'approve');

            $this->move($who, $doc, 'publish')->assertRedirect();

            $this->assertSame('published', $doc->fresh()->status, "{$label} could not publish");
        }
    }

    public function test_an_unapproved_document_cannot_be_published(): void
    {
        $doc = $this->submit($this->ehs());

        $this->move($this->ceo(), $doc, 'publish')->assertForbidden();

        $this->assertSame('pending_approval', $doc->fresh()->status);
    }

    // ===== 5. after publication =====

    private function published(): QualityDocument
    {
        $doc = $this->submit($this->ehs());
        $this->move($this->ceo(), $doc, 'approve');
        $this->move($this->ceo(), $doc, 'publish');

        return $doc->fresh();
    }

    public function test_the_ceo_can_update_a_published_document(): void
    {
        $doc = $this->published();

        $this->actingAs($this->ceo())
            ->put("/quality/documents/{$doc->id}", [
                'title' => 'Staff Handbook 2026',
                'category' => 'manual',
            ])
            ->assertRedirect();

        $doc->refresh();
        $this->assertSame('Staff Handbook 2026', $doc->title);
        $this->assertSame('manual', $doc->category);
    }

    public function test_the_ehs_officer_cannot_update_a_published_document(): void
    {
        $doc = $this->published();

        $this->actingAs($this->ehs())
            ->put("/quality/documents/{$doc->id}", ['title' => 'Rewritten', 'category' => 'form'])
            ->assertForbidden();

        $this->assertSame('Staff Handbook', $doc->fresh()->title);
    }

    public function test_the_ehs_officer_can_ask_for_a_published_document_to_be_removed(): void
    {
        $doc = $this->published();

        $this->move($this->ehs(), $doc, 'request_removal')->assertRedirect();

        // Asked for, not done: it is still the company's document until one of
        // the two offices takes it down.
        $this->assertSame('removal_requested', $doc->fresh()->status);
    }

    public function test_only_the_ceo_or_admin_actually_removes_it(): void
    {
        $doc = $this->published();
        $this->move($this->ehs(), $doc, 'request_removal');

        $this->move($this->ehs(), $doc, 'remove')->assertForbidden();
        $this->assertSame('removal_requested', $doc->fresh()->status);

        $this->move($this->admin(), $doc, 'remove')->assertRedirect();
        $this->assertSame('archived', $doc->fresh()->status);
        $this->assertNull($doc->fresh()->published_at);
    }

    public function test_deleting_keeps_the_record_and_drops_the_files(): void
    {
        $doc = $this->published();
        $path = $doc->currentFile()->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->ceo())->delete("/quality/documents/{$doc->id}")->assertRedirect();

        // The approval trail is the point of a controlled document, so the row
        // stays even when the file does not.
        $this->assertDatabaseHas('quality_documents', ['id' => $doc->id, 'status' => 'archived']);
        Storage::disk('local')->assertMissing($path);
    }

    // ===== 6. categories, and a preview =====

    public function test_a_published_document_keeps_the_category_it_was_given(): void
    {
        $doc = $this->published();

        $this->assertSame('policy', $doc->category);

        $this->actingAs($this->user([], 'staff@test.local'))
            ->get('/quality/library?category=policy')
            ->assertOk()
            ->assertSee('Staff Handbook', false);
    }

    public function test_the_library_can_be_filtered_to_one_category(): void
    {
        $this->published();

        $this->actingAs($this->user([], 'reader@test.local'))
            ->get('/quality/library?category=form')
            ->assertOk()
            ->assertDontSee('Staff Handbook', false);
    }

    public function test_a_published_file_can_be_previewed_in_the_browser(): void
    {
        $doc = $this->published();

        $response = $this->actingAs($this->user([], 'viewer@test.local'))
            ->get("/quality/documents/{$doc->id}/files/{$doc->currentFile()->id}/preview")
            ->assertOk();

        // Inline, not an attachment — that is the whole difference from the
        // download route beside it.
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_an_unpublished_file_is_not_previewable_by_staff(): void
    {
        $doc = $this->submit($this->ehs());

        $this->actingAs($this->user([], 'nosy@test.local'))
            ->get("/quality/documents/{$doc->id}/files/{$doc->currentFile()->id}/preview")
            ->assertForbidden();
    }

    // ===== 1. who may raise one at all =====

    public function test_only_the_three_offices_may_raise_a_document(): void
    {
        $payload = [
            'title' => 'Attempted',
            'category' => 'policy',
            'file' => UploadedFile::fake()->create('x.pdf', 4, 'application/pdf'),
        ];

        foreach (['quality-manager', 'auditor', 'hr-admin', 'manager', 'account-manager'] as $i => $role) {
            $this->actingAs($this->user([$role], "deny{$i}@test.local"))
                ->post('/quality/library', $payload)
                ->assertForbidden();
        }

        $this->assertSame(0, QualityDocument::count());
    }
}
