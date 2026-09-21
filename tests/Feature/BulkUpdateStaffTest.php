<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Filling in details on staff who already exist.
 *
 * 805 of 808 bank-transfer staff have no account number, and 904 of 905 have no
 * line manager. Entering that through the employee form one person at a time is
 * a week of work; the risk in doing it from a spreadsheet is that a typo creates
 * 805 duplicate people beside the real ones, or that a wrong account number
 * silently replaces a right one and pays a stranger.
 *
 * So: never create, never overwrite without being asked, and show everything
 * before writing anything.
 */
class BulkUpdateStaffTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    private function staff(string $number, array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'emp_number' => $number,
            'first_name' => 'Test',
            'last_name'  => 'Person '.$number,
            'hire_date'  => '2024-01-15',
            'status'     => 'active',
        ], $attributes));
    }

    private function sheet(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('update.csv', $csv);
    }

    /** Upload, then apply what the preview held. */
    private function runSheet(User $admin, string $csv, array $applyData = []): void
    {
        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), ['csv_file' => $this->sheet($csv)])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.apply'), $applyData)
            ->assertRedirect();
    }

    // ── Filling blanks ───────────────────────────────────────────────────

    public function test_a_bank_account_is_filled_in(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001', ['payment_mode' => 'bank']);

        $this->runSheet($admin, "emp_number,bank_account,bank_name\nHQ001,0140012345678,Stanbic Bank\n");

        $employee->refresh();

        $this->assertSame('0140012345678', $employee->bank_account);
        $this->assertSame('Stanbic Bank', $employee->bank_name);
    }

    public function test_a_line_manager_is_set_from_their_staff_number(): void
    {
        $admin = $this->admin();
        $manager = $this->staff('HQ002');
        $employee = $this->staff('HQ001');

        $this->runSheet($admin, "emp_number,manager_emp_number\nHQ001,HQ002\n");

        $this->assertSame($manager->id, $employee->refresh()->manager_id);
    }

    public function test_a_job_title_is_matched_by_name(): void
    {
        $admin = $this->admin();
        $designation = Designation::create(['title' => 'HR Manager']);
        $employee = $this->staff('HQ001');

        $this->runSheet($admin, "emp_number,designation\nHQ001,HR Manager\n");

        $this->assertSame($designation->id, $employee->refresh()->designation_id);
    }

    /** A blank cell means "leave this alone", so sheets can be sent piecemeal. */
    public function test_a_blank_cell_changes_nothing(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001', ['bank_account' => '0140012345678', 'phone' => '+256700000001']);

        $this->runSheet($admin, "emp_number,bank_account,phone,nssf_number\nHQ001,,,1234567890\n");

        $employee->refresh();

        $this->assertSame('0140012345678', $employee->bank_account);
        $this->assertSame('+256700000001', $employee->phone);
        $this->assertSame('1234567890', $employee->nssf_number);
    }

    public function test_columns_may_be_reordered_or_left_out(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001');

        $this->runSheet($admin, "tin_number,emp_number,phone\n1001008394,HQ001,+256700000001\n");

        $employee->refresh();

        $this->assertSame('1001008394', $employee->tin_number);
        $this->assertSame('+256700000001', $employee->phone);
    }

    public function test_friendlier_column_spellings_are_accepted(): void
    {
        $admin = $this->admin();
        $manager = $this->staff('HQ002');
        $employee = $this->staff('HQ001');

        $this->runSheet($admin, "staff_number,account_number,reports_to\nHQ001,0140012345678,HQ002\n");

        $employee->refresh();

        $this->assertSame('0140012345678', $employee->bank_account);
        $this->assertSame($manager->id, $employee->manager_id);
    }

    // ── Never creating ───────────────────────────────────────────────────

    /** A typo in a staff number must not add a second record beside a real one. */
    public function test_an_unknown_staff_number_creates_nobody(): void
    {
        $admin = $this->admin();
        $this->staff('HQ001');

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet("emp_number,bank_account\nHQ999,0140012345678\n"),
            ])
            ->assertOk()
            ->assertSee('No staff member has this number');

        $this->actingAs($admin)->post(route('admin.bulk-update.apply'));

        $this->assertSame(1, Employee::count());
        $this->assertNull(Employee::where('emp_number', 'HQ999')->first());
    }

    public function test_an_unknown_line_manager_is_reported_not_invented(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001');

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet("emp_number,manager_emp_number\nHQ001,NOBODY\n"),
            ])
            ->assertOk()
            ->assertSee('is not a staff number on the register');

        $this->actingAs($admin)->post(route('admin.bulk-update.apply'));

        $this->assertNull($employee->refresh()->manager_id);
        $this->assertSame(1, Employee::count());
    }

    public function test_an_unknown_job_title_is_reported_not_created(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001');

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet("emp_number,designation\nHQ001,Chief Wizard\n"),
            ])
            ->assertOk()
            ->assertSee('is not a job title on the system');

        $this->assertSame(0, Designation::count());
        $this->assertNull($employee->refresh()->designation_id);
    }

    public function test_somebody_cannot_be_their_own_line_manager(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001');

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet("emp_number,manager_emp_number\nHQ001,HQ001\n"),
            ])
            ->assertOk()
            ->assertSee('cannot be their own line manager');

        $this->actingAs($admin)->post(route('admin.bulk-update.apply'));

        $this->assertNull($employee->refresh()->manager_id);
    }

    // ── Never overwriting silently ───────────────────────────────────────

    /**
     * A bank account already on file was put there by somebody. Replacing it
     * with the wrong number pays a stranger, so it needs its own yes.
     */
    public function test_an_existing_value_is_left_alone_by_default(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001', ['bank_account' => '0140011111111']);

        $this->runSheet($admin, "emp_number,bank_account\nHQ001,0140099999999\n");

        $this->assertSame('0140011111111', $employee->refresh()->bank_account,
            'An existing account number was replaced without being asked.');
    }

    public function test_an_existing_value_is_replaced_when_asked(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001', ['bank_account' => '0140011111111']);

        $this->runSheet($admin, "emp_number,bank_account\nHQ001,0140099999999\n", ['allow_overwrite' => '1']);

        $this->assertSame('0140099999999', $employee->refresh()->bank_account);
    }

    /**
     * One disputed cell must not throw away the good data on the same row.
     */
    public function test_blanks_are_still_filled_on_a_row_that_also_overwrites(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001', ['bank_account' => '0140011111111']);

        $this->runSheet($admin, "emp_number,bank_account,tin_number\nHQ001,0140099999999,1001008394\n");

        $employee->refresh();

        $this->assertSame('0140011111111', $employee->bank_account, 'The existing value should stand.');
        $this->assertSame('1001008394', $employee->tin_number, 'The blank field should still have been filled.');
    }

    public function test_the_preview_separates_fills_from_overwrites(): void
    {
        $admin = $this->admin();
        $this->staff('HQ001', ['bank_account' => '0140011111111']);
        $this->staff('HQ002');

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet(
                    "emp_number,bank_account\nHQ001,0140099999999\nHQ002,0140022222222\n"
                ),
            ])
            ->assertOk()
            ->assertSee('would replace an existing value')
            ->assertSee('0140011111111')     // the from-value is shown
            ->assertSee('0140099999999');
    }

    // ── Nothing is written before the preview ────────────────────────────

    public function test_the_preview_alone_writes_nothing(): void
    {
        $admin = $this->admin();
        $employee = $this->staff('HQ001');

        $this->actingAs($admin)
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet("emp_number,bank_account\nHQ001,0140012345678\n"),
            ])
            ->assertOk();

        $this->assertNull($employee->refresh()->bank_account);
    }

    public function test_applying_without_a_preview_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.bulk-update.apply'))
            ->assertRedirect(route('admin.bulk-update.index'))
            ->assertSessionHas('error');
    }

    public function test_a_sheet_with_no_emp_number_column_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.bulk-update.preview'), [
                'csv_file' => $this->sheet("name,bank_account\nSomeone,0140012345678\n"),
            ])
            ->assertOk()
            ->assertSee('no emp_number column');
    }

    // ── Who may use it ───────────────────────────────────────────────────

    public function test_an_ordinary_employee_cannot_open_it(): void
    {
        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user)->get(route('admin.bulk-update.index'))->assertForbidden();
    }

    // ── The downloadable sheet ───────────────────────────────────────────

    public function test_the_template_comes_pre_filled_with_staff_numbers(): void
    {
        $admin = $this->admin();
        $this->staff('HQ001', ['payment_mode' => 'bank']);
        $this->staff('HQ002', ['payment_mode' => 'bank']);

        $csv = $this->actingAs($admin)
            ->get(route('admin.bulk-update.template'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('emp_number', $csv);
        $this->assertStringContainsString('HQ001', $csv);
        $this->assertStringContainsString('HQ002', $csv);
        $this->assertStringContainsString('Test Person HQ001', $csv,
            'The name is there so a human can see who each row is.');
    }

    public function test_the_template_can_be_narrowed_to_who_is_missing_an_account(): void
    {
        $admin = $this->admin();
        $this->staff('HQ001', ['payment_mode' => 'bank']);
        $this->staff('HQ002', ['payment_mode' => 'bank', 'bank_account' => '0140011111111']);

        $csv = $this->actingAs($admin)
            ->get(route('admin.bulk-update.template', ['missing' => 'bank_account']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('HQ001', $csv);
        $this->assertStringNotContainsString('HQ002', $csv,
            'Somebody who already has an account number does not need to be on the sheet.');
    }
}
