<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalKpi;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Printing the scorecard, and showing whose it is.
 *
 * The card is a document people sign and file. The working chrome around it —
 * the sidebar, the admin panel, the save buttons — belongs on screen, not on
 * the paper copy.
 */
class ScorecardPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $employee;
    private Appraisal $appraisal;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'account-manager', 'manager', 'md', 'client', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');

        $this->employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'emp_number' => 'HQ001',
            'first_name' => 'Ian',
            'last_name' => 'Kirabo',
            'hire_date' => '2024-01-15',
            'status' => 'active',
            'designation_id' => Designation::create(['title' => 'HR Manager'])->id,
            'department_id' => Department::create(['name' => 'Mastermind Head Office'])->id,
        ]);

        $this->appraisal = Appraisal::create([
            'title' => 'Quarterly Appraisal 2026',
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'initiated_by' => $this->admin->id,
            'status' => 'with_appraiser',
        ]);

        AppraisalKpi::create([
            'appraisal_id' => $this->appraisal->id,
            'perspective' => 'financial',
            'kra_name' => 'Customer Retention',
            'weightage' => 5,
            'sort_order' => 1,
        ]);
    }

    private function card(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get(route('appraisals.show', $this->appraisal));
    }

    public function test_the_card_offers_a_print_button(): void
    {
        $this->card()->assertOk()->assertSee('window.print()', false);
    }

    public function test_the_employee_photo_is_on_the_card(): void
    {
        // The alt text, not the src: Blade escapes the & in a query string to
        // &amp;, so asserting the raw URL tests the escaping, not the picture.
        $this->card()
            ->assertOk()
            ->assertSee('alt="Ian Kirabo"', false);
    }

    /**
     * Nobody has uploaded a photo, so the accessor falls back to an initials
     * avatar. The box is never empty on a card somebody signs.
     */
    public function test_somebody_with_no_photo_still_gets_one(): void
    {
        $this->assertStringContainsString('ui-avatars.com', $this->employee->avatar_url);

        $this->card()->assertOk()->assertSee('ui-avatars.com', false);
    }

    public function test_the_card_names_the_department(): void
    {
        $this->card()->assertOk()->assertSee('Mastermind Head Office');
    }

    // ── What stays off the paper ─────────────────────────────────────────

    public function test_the_working_controls_are_marked_not_to_print(): void
    {
        $html = $this->card()->assertOk()->getContent();

        // The admin panel and the save bar carry the class; the scorecard table
        // itself must not.
        $this->assertStringContainsString('no-print', $html);

        $adminPanel = strpos($html, 'Administrator controls');
        $this->assertNotFalse($adminPanel);

        $before = substr($html, max(0, $adminPanel - 400), 400);
        $this->assertStringContainsString('no-print', $before,
            'The administrator panel would print on the signed copy.');
    }

    public function test_the_print_rules_drop_the_sidebar_and_force_colour(): void
    {
        $html = $this->card()->assertOk()->getContent();

        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('aside, header, .no-print', $html);
        // A scorecard whose bands and ratings print white is unreadable.
        $this->assertStringContainsString('print-color-adjust: exact', $html);
    }

    public function test_the_scorecard_table_itself_still_prints(): void
    {
        $html = $this->card()->assertOk()->getContent();

        $table = strpos($html, 'Key Result Area');
        $this->assertNotFalse($table);

        $before = substr($html, max(0, $table - 600), 600);
        $this->assertStringNotContainsString('no-print', $before,
            'The scorecard table was marked not to print.');
    }
}
