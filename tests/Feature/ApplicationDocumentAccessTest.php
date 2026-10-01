<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may read an applicant's papers.
 *
 * These files are a stranger's CV, academic certificates, passport photo, LC
 * letter and police letter. They are stored off the web root, and the two
 * screens that linked to them used Storage::url() — a path on the public
 * disk, where they are not. That produced a 404, which was the lucky
 * outcome: had the files been there, the link would have handed anyone who
 * came by the URL somebody else's police letter with no login at all.
 */
class ApplicationDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function job(string $title = 'Cleaner'): JobPosting
    {
        $department = Department::firstOrCreate(['name' => 'Operations'], ['is_active' => true]);

        return JobPosting::create([
            'title'           => $title,
            'department_id'   => $department->id,
            'employment_type' => 'full_time',
            'description'     => 'Keep a site clean.',
            'vacancies'       => 1,
            'status'          => 'open',
            'is_public'       => true,
        ]);
    }

    private function candidateWithPapers(JobPosting $job): Candidate
    {
        $candidate = Candidate::create([
            'job_posting_id' => $job->id,
            'first_name'     => 'Grace',
            'last_name'      => 'Akello',
            'email'          => 'grace.akello@example.com',
            'status'         => 'new',
        ]);

        $path = "applications/{$job->id}/{$candidate->id}/police.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 police letter');

        $candidate->forceFill(['resume_path' => $path])->save();

        CandidateDocument::create([
            'candidate_id'  => $candidate->id,
            'type'          => 'police_letter',
            'path'          => $path,
            'original_name' => 'police.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 22,
        ]);

        return $candidate;
    }

    private function clientFor(JobPosting $job): User
    {
        $user   = $this->userWithRole('client');
        $client = Client::create([
            'user_id'        => $user->id,
            'company_name'   => 'Acme Ltd',
            'contact_person' => 'Someone',
        ]);

        DB::table('client_job_assignments')->insert([
            'client_id'      => $client->id,
            'job_posting_id' => $job->id,
            'assigned_by'    => $user->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return $user;
    }

    public function test_a_stranger_with_the_link_gets_nothing(): void
    {
        Storage::fake('local');

        $document = CandidateDocument::find(
            $this->candidateWithPapers($this->job())->documents()->value('id')
        );

        // Not signed in at all.
        $this->get(route('recruitment.applications.document', $document))
            ->assertRedirect(route('login'));
    }

    public function test_recruitment_staff_may_open_it(): void
    {
        Storage::fake('local');

        $candidate = $this->candidateWithPapers($this->job());
        $document  = $candidate->documents()->first();

        foreach (['hr-admin', 'recruiter', 'super-admin', 'md', 'manager'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('recruitment.applications.document', $document))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_an_ordinary_employee_may_not(): void
    {
        Storage::fake('local');

        $employee = $this->userWithRole('employee');
        Employee::create([
            'user_id'    => $employee->id,
            'emp_number' => 'MM' . $employee->id,
            'first_name' => 'Staff',
            'last_name'  => 'Member',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);

        $document = $this->candidateWithPapers($this->job())->documents()->first();

        // Being on the payroll is not a reason to read a stranger's papers.
        $this->actingAs($employee)
            ->get(route('recruitment.applications.document', $document))
            ->assertForbidden();
    }

    public function test_a_client_may_open_papers_on_their_own_posting(): void
    {
        Storage::fake('local');

        $job      = $this->job();
        $client   = $this->clientFor($job);
        $document = $this->candidateWithPapers($job)->documents()->first();

        $this->actingAs($client)
            ->get(route('recruitment.applications.document', $document))
            ->assertOk();
    }

    public function test_a_client_may_not_reach_another_clients_applicants(): void
    {
        Storage::fake('local');

        $theirs = $this->job('Cleaner');
        $mine   = $this->job('Security Guard');

        $client   = $this->clientFor($mine);
        $document = $this->candidateWithPapers($theirs)->documents()->first();

        // The page already refused this; the file must refuse it too, or the
        // page check is just a sign on an unlocked door.
        $this->actingAs($client)
            ->get(route('recruitment.applications.document', $document))
            ->assertForbidden();
    }

    public function test_the_cv_route_is_guarded_the_same_way(): void
    {
        Storage::fake('local');

        $job       = $this->job();
        $candidate = $this->candidateWithPapers($job);

        $this->get(route('recruitment.applications.cv', $candidate))
            ->assertRedirect(route('login'));

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->userWithRole('hr-admin'))
            ->get(route('recruitment.applications.cv', $candidate))
            ->assertOk();
    }

    public function test_a_missing_file_is_a_clear_404_not_a_crash(): void
    {
        Storage::fake('local');

        $candidate = $this->candidateWithPapers($this->job());
        $document  = $candidate->documents()->first();

        Storage::disk('local')->delete($document->path);

        $this->actingAs($this->userWithRole('hr-admin'))
            ->get(route('recruitment.applications.document', $document))
            ->assertNotFound();
    }

    public function test_an_applicant_with_no_cv_does_not_500(): void
    {
        Storage::fake('local');

        $candidate = Candidate::create([
            'job_posting_id' => $this->job()->id,
            'first_name'     => 'No',
            'last_name'      => 'Papers',
            'email'          => 'no.papers@example.com',
            'status'         => 'new',
        ]);

        $this->actingAs($this->userWithRole('hr-admin'))
            ->get(route('recruitment.applications.cv', $candidate))
            ->assertNotFound();
    }
}
