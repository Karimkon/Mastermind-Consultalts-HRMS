<?php

namespace Tests\Feature;

use App\Mail\LeaveCoverNominatedMail;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Naming the person who will cover your leave.
 *
 * It used to be three free-text boxes. A typed name could not be notified,
 * could not see the request on their own dashboard, and disagreed with Employee
 * Central about spelling — "Flo" is not a record of anything. The cover person
 * is now chosen from the staff register, and hears about it on submission
 * rather than days later when the request is finally approved.
 */
class LeaveCoverPersonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // NotificationService asks Spatie for these by name when a leave is
        // submitted, and Spatie throws rather than returning nobody.
        foreach (['hr-admin', 'super-admin', 'manager'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function staff(string $first, string $last, ?string $email = 'x@example.test'): Employee
    {
        static $n = 0;
        $n++;

        $user = $email ? User::factory()->create(['name' => "$first $last", 'email' => "u{$n}-{$email}"]) : null;

        return Employee::create([
            'user_id' => $user?->id,
            'emp_number' => 'EMP-'.$n,
            'first_name' => $first,
            'last_name' => $last,
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
    }

    private function leaveType(): LeaveType
    {
        return LeaveType::firstOrCreate(
            ['name' => 'Annual Leave'],
            ['code' => 'ANNUAL', 'days_allowed' => 21, 'is_active' => true]
        );
    }

    private function payload(Employee $cover, array $extra = []): array
    {
        return array_merge([
            'leave_type_id' => $this->leaveType()->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-09',
            'reason' => 'Family commitment upcountry.',
            'replacement_employee_id' => $cover->id,
        ], $extra);
    }

    // ── The picker ───────────────────────────────────────────────────────

    public function test_the_cover_person_is_recorded_as_a_real_staff_member(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Florence', 'Nakibuuka');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover))
            ->assertRedirect();

        $leave = LeaveRequest::firstOrFail();

        $this->assertSame($cover->id, $leave->replacement_employee_id);
        $this->assertSame('Florence Nakibuuka', $leave->replacement_name,
            'The name is taken from the register, not from whatever was typed.');
    }

    /** Contact details come from their record, so the email can actually arrive. */
    public function test_their_email_is_resolved_from_their_staff_record(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Florence', 'Nakibuuka');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover))
            ->assertRedirect();

        $this->assertSame($cover->user->email, LeaveRequest::firstOrFail()->replacement_email);
    }

    public function test_a_typed_address_overrides_the_record(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Florence', 'Nakibuuka');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover, [
                'replacement_email' => 'florence.personal@example.test',
            ]))
            ->assertRedirect();

        $this->assertSame('florence.personal@example.test', LeaveRequest::firstOrFail()->replacement_email);
    }

    // ── Refusals ─────────────────────────────────────────────────────────

    public function test_a_cover_person_must_be_chosen(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), array_diff_key(
                $this->payload($applicant), ['replacement_employee_id' => null]
            ))
            ->assertSessionHasErrors('replacement_employee_id');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_you_cannot_nominate_yourself(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($applicant))
            ->assertSessionHasErrors('replacement_employee_id');

        $this->assertSame(0, LeaveRequest::count());
    }

    /**
     * Said at submission, not discovered when the mail silently fails.
     */
    public function test_somebody_with_no_email_is_refused_with_a_reason(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Silent', 'Worker', null);

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover))
            ->assertSessionHasErrors('replacement_email');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_they_can_still_be_nominated_by_typing_an_address(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Silent', 'Worker', null);

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover, [
                'replacement_email' => 'reachable@example.test',
            ]))
            ->assertRedirect();

        $this->assertSame(1, LeaveRequest::count());
    }

    // ── Telling them ─────────────────────────────────────────────────────

    public function test_the_cover_person_is_emailed_on_submission(): void
    {
        Mail::fake();

        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Florence', 'Nakibuuka');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover))
            ->assertRedirect();

        Mail::assertSent(LeaveCoverNominatedMail::class, function ($mail) use ($cover) {
            return $mail->hasTo($cover->user->email);
        });
    }

    public function test_the_cover_person_gets_an_in_app_notification(): void
    {
        Mail::fake();

        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Florence', 'Nakibuuka');

        $this->actingAs($applicant->user)
            ->post(route('leaves.store'), $this->payload($cover));

        $note = Notification::where('user_id', $cover->user->id)
            ->where('type', 'leave_cover_nominated')->first();

        $this->assertNotNull($note, 'The nominated person was never told.');
        $this->assertStringContainsString('Ian Kirabo', $note->body);
        $this->assertStringContainsString('pending approval', $note->body);
    }

    /** The subject must name the colleague; it used to come out blank. */
    public function test_the_subject_names_the_colleague(): void
    {
        $applicant = $this->staff('Ian', 'Kirabo');
        $cover = $this->staff('Florence', 'Nakibuuka');

        $leave = LeaveRequest::create([
            'employee_id' => $applicant->id,
            'leave_type_id' => $this->leaveType()->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-09',
            'days_count' => 5,
            'reason' => 'Family commitment.',
            'replacement_employee_id' => $cover->id,
            'replacement_name' => $cover->full_name,
            'replacement_email' => $cover->user->email,
            'status' => 'pending',
        ]);

        $this->assertSame(
            'You have been nominated to cover for Ian Kirabo',
            (new LeaveCoverNominatedMail($leave))->envelope()->subject
        );

        $this->assertStringContainsString(
            'Ian Kirabo',
            (new \App\Mail\LeaveReplacementMail($leave))->envelope()->subject,
            'LeaveReplacementMail used $leave instead of $this->leave, so the name was dropped.'
        );
    }

    // ── The search behind the picker ─────────────────────────────────────

    public function test_search_finds_people_by_their_employee_record_name(): void
    {
        $me = $this->staff('Ian', 'Kirabo');
        $this->staff('Florence', 'Nakibuuka', null);   // no login at all

        $results = $this->actingAs($me->user)
            ->getJson('/ajax/employees/search?q=Florence')
            ->assertOk()
            ->json('results');

        $this->assertCount(1, $results, 'Staff without a user account were invisible to the search.');
        $this->assertStringContainsString('Florence Nakibuuka', $results[0]['text']);
    }

    public function test_search_can_exclude_the_person_doing_the_searching(): void
    {
        $me = $this->staff('Ian', 'Kirabo');

        $withSelf = $this->actingAs($me->user)
            ->getJson('/ajax/employees/search?q=Ian')->json('results');
        $withoutSelf = $this->actingAs($me->user)
            ->getJson('/ajax/employees/search?q=Ian&exclude_self=1')->json('results');

        $this->assertCount(1, $withSelf);
        $this->assertCount(0, $withoutSelf);
    }

    /** The endpoint is open to every signed-in user; it must not hand out contacts. */
    public function test_search_does_not_leak_contact_details(): void
    {
        $me = $this->staff('Ian', 'Kirabo');
        $other = $this->staff('Florence', 'Nakibuuka');

        $results = $this->actingAs($me->user)
            ->getJson('/ajax/employees/search?q=Florence')->json('results');

        $this->assertSame(['id', 'text', 'avatar'], array_keys($results[0]));
        $this->assertStringNotContainsString($other->user->email, json_encode($results));
    }
}
