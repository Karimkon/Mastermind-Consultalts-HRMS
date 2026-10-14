<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The list behind the "Expiring Documents" number on the dashboard.
 *
 * The figure was counted and shown with nowhere to tap. The one number on that
 * screen whose entire purpose is to prompt an action -- a work permit or
 * contract about to lapse -- was a dead end, so the only way to find out WHICH
 * document it meant was to open employees one by one.
 *
 * Scope is the thing to get right: an account manager sees documents belonging
 * to their own clients' staff, and nobody else's.
 */
class ExpiringDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $am;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['account-manager', 'employee'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $this->am = User::factory()->create(['status' => 'active', 'name' => 'Kansiime Brenda']);
        $this->am->assignRole('account-manager');
    }

    private function clientFor(User $manager, string $name): Client
    {
        return Client::create([
            'company_name' => $name,
            'contact_person' => 'Someone',
            'account_manager_id' => $manager->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function staff(Client $client, string $first, string $last): Employee
    {
        $e = Employee::create([
            'emp_number' => 'E'.fake()->unique()->numberBetween(10000, 99999),
            'first_name' => $first,
            'last_name' => $last,
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
        $client->employees()->attach($e->id, ['assigned_by' => $this->am->id]);

        return $e;
    }

    private function doc(Employee $e, string $title, string $expiry): EmployeeDocument
    {
        return EmployeeDocument::create([
            'employee_id' => $e->id,
            'document_type' => 'contract',
            'title' => $title,
            'file_path' => 'docs/x.pdf',
            'file_name' => 'x.pdf',
            'expiry_date' => $expiry,
        ]);
    }

    public function test_it_lists_documents_expiring_soon(): void
    {
        $client = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $worker = $this->staff($client, 'Sande', 'Worker');

        $this->doc($worker, 'Work Permit', now()->addDays(10)->toDateString());
        $this->doc($worker, 'Contract', now()->addDays(200)->toDateString());

        $body = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents')
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['total'], 'only the one inside the 30 day window');
        $this->assertSame('Work Permit', $body['data'][0]['title']);
        $this->assertSame('Sande Worker', $body['data'][0]['employee']);
        $this->assertSame('Roofings Uganda Limited', $body['data'][0]['company']);
        $this->assertFalse($body['data'][0]['expired']);
    }

    public function test_an_already_expired_document_is_included_and_flagged(): void
    {
        // The one most likely to be forgotten, so it must not drop off the list.
        $client = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $worker = $this->staff($client, 'Sande', 'Worker');

        $this->doc($worker, 'Lapsed Permit', now()->subDays(7)->toDateString());

        $body = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents')
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['total']);
        $this->assertTrue($body['data'][0]['expired']);
        $this->assertLessThan(0, $body['data'][0]['days_left']);
        $this->assertSame(1, $body['expired_count']);
    }

    public function test_expired_documents_sort_before_ones_still_valid(): void
    {
        $client = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $worker = $this->staff($client, 'Sande', 'Worker');

        $this->doc($worker, 'Expires Later', now()->addDays(20)->toDateString());
        $this->doc($worker, 'Already Lapsed', now()->subDays(5)->toDateString());

        $body = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents')
            ->json();

        $this->assertSame('Already Lapsed', $body['data'][0]['title']);
        $this->assertSame('Expires Later', $body['data'][1]['title']);
    }

    public function test_it_shows_nothing_belonging_to_another_managers_staff(): void
    {
        /*
         | The scope check. Account managers reach staff through their clients,
         | and a document list that forgot that would leak another manager's
         | people -- the same leak that was found in leave visibility.
         */
        $mine = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $worker = $this->staff($mine, 'My', 'Worker');
        $this->doc($worker, 'Mine', now()->addDays(5)->toDateString());

        $other = User::factory()->create(['status' => 'active', 'name' => 'Omar Shamilah']);
        $other->assignRole('account-manager');
        $theirs = $this->clientFor($other, 'Marriott Hotel');
        $theirWorker = $this->staff($theirs, 'Their', 'Worker');
        $this->doc($theirWorker, 'Theirs', now()->addDays(5)->toDateString());

        $body = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents')
            ->json();

        $this->assertSame(1, $body['total']);
        $this->assertSame('Mine', $body['data'][0]['title']);
    }

    public function test_the_window_can_be_widened(): void
    {
        $client = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $worker = $this->staff($client, 'Sande', 'Worker');

        $this->doc($worker, 'Ninety Days Out', now()->addDays(90)->toDateString());

        $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents')
            ->assertJsonPath('total', 0);

        $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents?days=120')
            ->assertJsonPath('total', 1);
    }

    public function test_it_can_be_filtered_to_one_client(): void
    {
        $a = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $b = $this->clientFor($this->am, 'Uganda Baati');

        $this->doc($this->staff($a, 'First', 'Worker'), 'Roofings Doc', now()->addDays(5)->toDateString());
        $this->doc($this->staff($b, 'Second', 'Worker'), 'Baati Doc', now()->addDays(5)->toDateString());

        $body = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents?client_id='.$b->id)
            ->json();

        $this->assertSame(1, $body['total']);
        $this->assertSame('Baati Doc', $body['data'][0]['title']);
    }

    public function test_the_count_matches_what_the_dashboard_shows(): void
    {
        // The list and the number must agree, or the badge sends people
        // looking for something that is not there.
        $client = $this->clientFor($this->am, 'Roofings Uganda Limited');
        $worker = $this->staff($client, 'Sande', 'Worker');

        $this->doc($worker, 'Soon A', now()->addDays(3)->toDateString());
        $this->doc($worker, 'Soon B', now()->addDays(29)->toDateString());
        $this->doc($worker, 'Far Off', now()->addDays(90)->toDateString());

        $dashboard = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/dashboard')
            ->assertOk()
            ->json('stats.expiring_docs');

        $listed = $this->actingAs($this->am, 'sanctum')
            ->getJson('/api/account-manager/expiring-documents')
            ->json('total');

        $this->assertSame(2, $dashboard);
        $this->assertSame($dashboard, $listed);
    }
}
