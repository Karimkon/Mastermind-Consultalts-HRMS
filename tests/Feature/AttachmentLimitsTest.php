<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Pip;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What an attachment may be, and who may see one.
 *
 * The size and type were written out separately in each controller, with the
 * wording on screen ("up to 10MB") typed beside them by hand — two statements
 * of one fact that could drift apart, and had. A 205MB clip was refused, but
 * only after the whole thing had finished uploading, because a size rule can
 * only run once the file has arrived.
 *
 * The PIP list is here too: it showed every improvement plan in the company to
 * anyone who opened it, on a route the employee role can reach.
 */
class AttachmentLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function employeeFor(User $user, string $first = 'Sande'): Employee
    {
        return Employee::create([
            'user_id'    => $user->id,
            'emp_number' => 'MM' . $user->id,
            'first_name' => $first,
            'last_name'  => 'Test',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);
    }

    private function pipFor(Employee $employee, ?User $author = null): Pip
    {
        return Pip::create([
            'employee_id' => $employee->id,
            'title'       => 'Improve loading times',
            'start_date'  => now()->subDay(),
            'end_date'    => now()->addMonth(),
            'objectives'  => ['Damages during loading'],
            'status'      => 'active',
            'created_by'  => $author?->id ?? $this->userWithRole('hr-admin')->id,
        ]);
    }

    // ── The limit itself ────────────────────────────────────────────────────

    public function test_the_rule_and_the_wording_come_from_the_same_place(): void
    {
        config(['uploads.max_attachment_mb' => 64]);

        $this->assertSame(64, Uploads::maxMb());
        $this->assertSame(64 * 1024, Uploads::maxKb());
        $this->assertContains('max:65536', Uploads::rules());

        // The sentence under the field is generated, not typed, so it cannot
        // say 10MB while the rule enforces 64.
        $this->assertStringContainsString('64MB', Uploads::hint());
        $this->assertStringContainsString('64MB', Uploads::messages()['file.max']);
    }

    public function test_the_accept_attribute_covers_every_allowed_extension(): void
    {
        $accept = Uploads::accept();

        foreach (Uploads::extensions() as $ext) {
            $this->assertStringContainsString('.' . $ext, $accept,
                "The file picker must offer .{$ext}, or somebody cannot select what the server accepts.");
        }
    }

    public function test_video_is_accepted_now(): void
    {
        Storage::fake('local');
        $hr  = $this->userWithRole('hr-admin');
        $pip = $this->pipFor($this->employeeFor($this->userWithRole('employee')), $hr);

        // The kind of file that was refused: a phone clip used as evidence.
        $clip = UploadedFile::fake()->create('loading-bay.mp4', 2048, 'video/mp4');

        $this->actingAs($hr)
            ->post("/pips/{$pip->id}/attachments", ['file' => $clip, 'objective_index' => 0])
            ->assertRedirect();

        $this->assertSame(1, $pip->attachments()->count());
    }

    public function test_a_file_over_the_limit_is_still_refused(): void
    {
        Storage::fake('local');
        config(['uploads.max_attachment_mb' => 8]);

        $hr  = $this->userWithRole('hr-admin');
        $pip = $this->pipFor($this->employeeFor($this->userWithRole('employee')), $hr);

        $this->actingAs($hr)
            ->post("/pips/{$pip->id}/attachments", [
                'file' => UploadedFile::fake()->create('huge.mp4', 9 * 1024, 'video/mp4'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $pip->attachments()->count());
    }

    public function test_an_executable_is_refused_whatever_its_size(): void
    {
        Storage::fake('local');
        $hr  = $this->userWithRole('hr-admin');
        $pip = $this->pipFor($this->employeeFor($this->userWithRole('employee')), $hr);

        $this->actingAs($hr)
            ->post("/pips/{$pip->id}/attachments", [
                'file' => UploadedFile::fake()->create('payload.exe', 12),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $pip->attachments()->count());
    }

    // ── Who sees which plans ────────────────────────────────────────────────

    public function test_an_employee_sees_only_their_own_improvement_plans(): void
    {
        $mine   = $this->userWithRole('employee');
        $theirs = $this->userWithRole('employee');

        $this->pipFor($this->employeeFor($mine, 'Sande'));
        $this->pipFor($this->employeeFor($theirs, 'Ashiraf'));

        $response = $this->actingAs($mine)->get('/pips')->assertOk();

        $pips = $response->viewData('pips');
        $this->assertCount(1, $pips, 'A plan names somebody and says what they are failing at.');
        $this->assertSame('Sande', $pips->first()->employee->first_name);
    }

    public function test_hr_still_sees_every_plan(): void
    {
        $this->pipFor($this->employeeFor($this->userWithRole('employee'), 'Sande'));
        $this->pipFor($this->employeeFor($this->userWithRole('employee'), 'Ashiraf'));

        $response = $this->actingAs($this->userWithRole('hr-admin'))->get('/pips')->assertOk();

        $this->assertCount(2, $response->viewData('pips'));
    }

    public function test_a_supervisor_sees_the_plan_they_opened(): void
    {
        $supervisor = $this->userWithRole('employee');
        $this->employeeFor($supervisor, 'Supervisor');

        $staff = $this->userWithRole('employee');
        $this->pipFor($this->employeeFor($staff, 'Ashiraf'), $supervisor);

        $response = $this->actingAs($supervisor)->get('/pips')->assertOk();

        $this->assertCount(1, $response->viewData('pips'),
            'Whoever opened the plan has to be able to follow it up.');
    }
}
