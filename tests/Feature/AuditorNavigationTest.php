<?php

namespace Tests\Feature;

use App\Models\QualityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The appointed auditor could not see Quality Management at all.
 *
 * Same shape as the quality-manager bug in QualityManagerNavigationTest, one
 * role later. Every /quality route already admitted `auditor` - the middleware
 * had listed it since phase 2 - but the sidebar gate read
 * `@role('super-admin|hr-admin|manager|quality-manager')`, so the person
 * appointed to audit the system was shown no Quality menu whatsoever.
 *
 * Widening that gate is only half a fix. QualityDocumentController gated the
 * document register on canManage(), which deliberately excludes the auditor, so
 * the newly visible Document Control link would have led straight to a 403.
 * Hence canView()/canEdit(): an auditor READS the register and the files in it,
 * and writes to nothing.
 *
 * These tests walk the role rather than trusting the gate, because actingAs()
 * starts you inside and a role locked out at the front door is invisible to a
 * green suite.
 */
class AuditorNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Every screen the Quality group links to. */
    private const QUALITY_SCREENS = [
        '/quality',
        '/quality/checks',
        '/quality/standards',
        '/quality/nonconformities',
        '/quality/audits',
        '/quality/audits/create',
        '/quality/compliance',
        '/quality/reports',
        '/quality/reviews',
        '/quality/documents',
    ];

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate('employee', 'web');

        $user = User::create([
            'name'     => 'Test '.$role,
            'email'    => str_replace('-', '', $role).'@test.local',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole(['employee', $role]);

        return $user;
    }

    private function document(User $initiator, string $status = 'draft'): QualityDocument
    {
        return QualityDocument::create([
            'doc_number'   => QualityDocument::nextNumber(),
            'title'        => 'Quality Manual',
            'category'     => 'manual',
            'status'       => $status,
            'initiator_id' => $initiator->id,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    // ===== What the auditor gains =====

    /** The point of the fix: the menu is actually on the page. */
    public function test_the_auditor_is_given_the_quality_menu(): void
    {
        $response = $this->actingAs($this->userWithRole('auditor'))
            ->get('/quality/library')
            ->assertOk();

        $response->assertSee('>Quality<', false);
        $response->assertSee('Quality Management', false);
        $response->assertSee('Document Control', false);
    }

    public function test_the_auditor_can_open_every_quality_screen(): void
    {
        $auditor = $this->userWithRole('auditor');

        foreach (self::QUALITY_SCREENS as $url) {
            $this->actingAs($auditor)->get($url)
                ->assertOk("Auditor was refused {$url}");
        }
    }

    public function test_the_auditor_can_read_the_document_register(): void
    {
        $manager = $this->userWithRole('quality-manager');
        $this->document($manager);

        $response = $this->actingAs($this->userWithRole('auditor'))
            ->get('/quality/documents')
            ->assertOk();

        // Reading the register means seeing what is in it, not just a 200.
        $response->assertSee('Quality Manual', false);
        $response->assertSee('open to you for review', false);
    }

    public function test_the_auditor_can_open_a_document_they_have_no_part_in(): void
    {
        $manager = $this->userWithRole('quality-manager');
        $doc = $this->document($manager);

        $response = $this->actingAs($this->userWithRole('auditor'))
            ->get("/quality/documents/{$doc->id}")
            ->assertOk();

        $response->assertSee('Quality Manual', false);
        $response->assertSee('read access to this document', false);
    }

    // ===== What the auditor must never gain =====

    public function test_the_auditor_cannot_author_or_move_a_document(): void
    {
        $manager = $this->userWithRole('quality-manager');
        $doc = $this->document($manager);
        $auditor = $this->userWithRole('auditor');

        $this->actingAs($auditor)->get('/quality/documents/create')->assertForbidden();
        $this->actingAs($auditor)->post('/quality/documents', [
            'title' => 'Sneaky', 'category' => 'policy',
        ])->assertForbidden();
        $this->actingAs($auditor)->post("/quality/documents/{$doc->id}/transition", [
            'action' => 'publish',
        ])->assertForbidden();

        $this->assertSame('draft', $doc->fresh()->status, 'The auditor changed the document status.');
    }

    public function test_the_auditor_is_shown_no_write_controls(): void
    {
        $manager = $this->userWithRole('quality-manager');
        $doc = $this->document($manager);

        $auditor = $this->userWithRole('auditor');

        $response = $this->actingAs($auditor)
            ->get("/quality/documents/{$doc->id}")
            ->assertOk();

        $response->assertDontSee('Upload new version', false);
        $response->assertDontSee('Send for editing', false);
        $response->assertDontSee('Send for approval', false);

        $this->actingAs($auditor)
            ->get('/quality/documents')
            ->assertDontSee('New document', false);
    }

    public function test_the_auditor_cannot_appoint_the_quality_team(): void
    {
        $this->actingAs($this->userWithRole('auditor'))
            ->get('/quality/team')
            ->assertForbidden();
    }

    // ===== Regression: the quality manager keeps everything =====

    public function test_the_quality_manager_still_authors_documents(): void
    {
        $manager = $this->userWithRole('quality-manager');
        $doc = $this->document($manager);

        $this->actingAs($manager)->get('/quality/documents/create')->assertOk();

        $response = $this->actingAs($manager)->get("/quality/documents/{$doc->id}")->assertOk();
        $response->assertSee('Upload new version', false);
    }

    /** An ordinary employee is still outside all of it. */
    public function test_an_ordinary_employee_sees_no_quality_section(): void
    {
        Role::findOrCreate('employee', 'web');

        $user = User::create([
            'name'     => 'Ordinary',
            'email'    => 'ordinary@test.local',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole('employee');

        $this->actingAs($user)->get('/quality/library')
            ->assertOk()
            ->assertDontSee('Document Control', false);

        $this->actingAs($user)->get('/quality/documents')->assertForbidden();
        $this->actingAs($user)->get('/quality/audits')->assertForbidden();
    }
}
