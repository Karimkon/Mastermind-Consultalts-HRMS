<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobCategory;
use App\Models\JobPosting;
use App\Models\JobSeeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The careers app: an outsider applying, and the wall between them and the
 * company's own data.
 *
 * The wall is the part worth guarding. Sanctum registers its `sanctum` guard
 * with no provider, and with no provider it accepts a token belonging to any
 * model that issues tokens — so a job seeker's token was accepted on
 * auth:sanctum and /api/employees answered it with the employee list.
 */
class CareersAppTest extends TestCase
{
    use RefreshDatabase;

    private function openJob(?int $categoryId = null): JobPosting
    {
        $department = Department::create(['name' => 'Operations', 'is_active' => true]);

        return JobPosting::create([
            'title'           => 'Cleaner',
            'department_id'   => $department->id,
            'job_category_id' => $categoryId,
            'employment_type' => 'full_time',
            'location'        => 'Kampala',
            'description'     => 'Keep a site clean.',
            'vacancies'       => 4,
            'status'          => 'open',
            'is_public'       => true,
        ]);
    }

    private function seekerToken(JobSeeker $seeker): string
    {
        return $seeker->createToken('test')->plainTextToken;
    }

    private function seeker(array $attributes = []): JobSeeker
    {
        return JobSeeker::create(array_merge([
            'name'     => 'Brian Okello',
            'email'    => 'brian.okello@example.com',
            'phone'    => '0772555444',
            'password' => 'secret-password',
        ], $attributes));
    }

    public function test_the_job_list_is_open_before_anybody_has_an_account(): void
    {
        $this->openJob();

        // This is what the app opens on. If it needed a token, somebody who
        // had just installed the app would see a login form and nothing else.
        $this->getJson('/api/careers/jobs')
            ->assertOk()
            ->assertJsonPath('jobs.0.title', 'Cleaner')
            ->assertJsonStructure(['jobs', 'meta', 'top_categories']);
    }

    public function test_a_draft_posting_is_not_on_the_public_list(): void
    {
        $this->openJob()->forceFill(['is_public' => false])->save();

        $this->getJson('/api/careers/jobs')->assertOk()->assertJsonCount(0, 'jobs');
    }

    public function test_registering_records_the_categories_chosen(): void
    {
        $security = JobCategory::create(['name' => 'Security', 'slug' => 'security']);
        $driving  = JobCategory::create(['name' => 'Driving', 'slug' => 'driving']);

        $response = $this->postJson('/api/careers/register', [
            'name'                  => 'Brian Okello',
            'email'                 => 'brian.okello@example.com',
            'phone'                 => '0772555444',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
            'categories'            => [$security->id, $driving->id],
        ])->assertCreated();

        $this->assertNotEmpty($response->json('token'));
        $this->assertCount(2, $response->json('seeker.categories'));

        // The point of the account: a new vacancy in one of these reaches them.
        $seeker = JobSeeker::where('email', 'brian.okello@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$security->id, $driving->id],
            $seeker->categories()->pluck('job_categories.id')->all()
        );
    }

    public function test_a_job_seeker_is_not_a_user(): void
    {
        $this->postJson('/api/careers/register', [
            'name'                  => 'Brian Okello',
            'email'                 => 'brian.okello@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        // Nothing about applying for a cleaning job should create a row in the
        // table that drives payroll, leave and roles.
        $this->assertDatabaseMissing('users', ['email' => 'brian.okello@example.com']);
        $this->assertDatabaseHas('job_seekers', ['email' => 'brian.okello@example.com']);
    }

    public function test_a_seeker_token_cannot_reach_the_company_data(): void
    {
        $token = $this->seekerToken($this->seeker());

        foreach (['/api/employees', '/api/my-payslips', '/api/dashboard', '/api/leaves'] as $uri) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson($uri)
                ->assertStatus(401, "A job seeker reached {$uri}");
        }
    }

    public function test_an_employee_token_cannot_reach_the_careers_account(): void
    {
        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('employee');

        Employee::create([
            'user_id'    => $user->id,
            'emp_number' => 'MM' . $user->id,
            'first_name' => 'Staff',
            'last_name'  => 'Member',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $user->createToken('t')->plainTextToken)
            ->getJson('/api/careers/me')
            ->assertStatus(401);
    }

    public function test_applying_from_the_app_stores_every_document(): void
    {
        Mail::fake();
        Storage::fake('local');

        $job    = $this->openJob();
        $seeker = $this->seeker();
        $token  = $this->seekerToken($seeker);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/careers/jobs/{$job->id}/apply", [
                'cover_letter' => 'I have four years on a site in Kampala.',
                'cv'           => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
                'documents'    => [
                    'academic'       => [
                        UploadedFile::fake()->create('olevel.pdf', 40, 'application/pdf'),
                        UploadedFile::fake()->create('alevel.pdf', 40, 'application/pdf'),
                    ],
                    'passport_photo' => UploadedFile::fake()->image('me.jpg'),
                    'lc_letter'      => UploadedFile::fake()->create('lc.pdf', 20, 'application/pdf'),
                    'police_letter'  => UploadedFile::fake()->create('police.pdf', 20, 'application/pdf'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $candidate = Candidate::where('job_seeker_id', $seeker->id)->firstOrFail();

        // The CV plus five others. `candidates.resume_path` holds one file and
        // that is all it can ever hold, which is why each is now a row.
        $this->assertSame(6, $candidate->documents()->count());
        $this->assertSame(2, $candidate->documents()->where('type', 'academic')->count());
        $this->assertNotNull($candidate->resume_path);

        $this->assertNotEmpty($response->json('application.tracking_code'));
        $this->assertSame('applied', $response->json('application.stage'));
    }

    public function test_applying_twice_for_the_same_job_is_refused(): void
    {
        Mail::fake();
        Storage::fake('local');

        $job   = $this->openJob();
        $token = $this->seekerToken($this->seeker());

        $apply = fn () => $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/careers/jobs/{$job->id}/apply", [
                'cv' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
            ], ['Accept' => 'application/json']);

        $apply()->assertCreated();

        // Pressing Submit twice on a slow connection must not create a second
        // application, or the applicant gets two of every message that follows.
        $apply()->assertStatus(409);
        $this->assertSame(1, Candidate::count());
    }

    public function test_the_applicant_sees_their_own_score(): void
    {
        Mail::fake();
        Storage::fake('local');

        $job    = $this->openJob();
        $seeker = $this->seeker();
        $token  = $this->seekerToken($seeker);

        $criteria = \App\Models\ShortlistingCriteria::create([
            'job_posting_id' => $job->id,
            'title'          => 'Screening',
            'top_n'          => 2,
            'is_active'      => true,
        ]);

        $question = \App\Models\ShortlistingQuestion::create([
            'criteria_id'    => $criteria->id,
            'question'       => 'Can you work night shifts?',
            'question_type'  => 'yes_no',
            'correct_answer' => 'yes',
            'weight'         => 4,
            'sort_order'     => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/careers/jobs/{$job->id}/apply", [
                'cv'        => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
                'screening' => [$question->id => 'yes'],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        // They answered the questions; hiding the mark from them while showing
        // it to the recruiter was never defensible.
        $this->assertSame(100.0, (float) $response->json('application.assessment.percentage'));
    }

    public function test_one_seeker_cannot_open_another_seekers_application(): void
    {
        Mail::fake();
        Storage::fake('local');

        $job = $this->openJob();

        $mine = $this->seeker();
        $this->withHeader('Authorization', 'Bearer ' . $this->seekerToken($mine))
            ->post("/api/careers/jobs/{$job->id}/apply", [
                'cv' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();

        $candidate = Candidate::where('job_seeker_id', $mine->id)->firstOrFail();

        $stranger = $this->seeker(['email' => 'stranger@example.com']);

        // A fresh guard, because the test kernel keeps the one the previous
        // request resolved - without this the second call is still the first
        // seeker, and the test would pass or fail for the wrong reason.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $this->seekerToken($stranger))
            ->getJson("/api/careers/applications/{$candidate->id}")
            ->assertStatus(404);
    }

    public function test_tracking_by_reference_needs_the_reference(): void
    {
        $job = $this->openJob();

        $candidate = Candidate::create([
            'job_posting_id' => $job->id,
            'first_name'     => 'Grace',
            'last_name'      => 'Akello',
            'email'          => 'grace@example.com',
            'status'         => 'screening',
        ]);

        $this->getJson("/api/careers/track/{$candidate->tracking_code}")
            ->assertOk()
            ->assertJsonPath('application.stage', 'screening')
            // The internal id is withheld on the public route: it is the one
            // thing that would let somebody walk the other applications.
            ->assertJsonPath('application.id', null);

        $this->getJson('/api/careers/track/AAAAAAAAAAAA')->assertNotFound();
    }

    public function test_a_profile_save_without_categories_does_not_unsubscribe(): void
    {
        $category = JobCategory::create(['name' => 'Security', 'slug' => 'security']);
        $seeker   = $this->seeker();
        $seeker->categories()->sync([$category->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->seekerToken($seeker))
            ->putJson('/api/careers/me', ['name' => 'Brian O.'])
            ->assertOk();

        $this->assertSame(1, $seeker->fresh()->categories()->count());
    }
}
