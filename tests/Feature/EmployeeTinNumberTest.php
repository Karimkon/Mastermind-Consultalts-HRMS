<?php

namespace Tests\Feature;

use App\Imports\EmployeesImport;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * employees.tax_number is retired. It was never populated on a single one of
 * the 1,325 production employees, no payroll code reads it, and in Uganda the
 * TIN *is* the number PAYE is filed against — there is no second number. These
 * pin that every way in writes tin_number instead.
 */
class EmployeeTinNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The importer puts every new hire in the 'employee' role.
        Role::findOrCreate('employee', 'web');
    }

    private function sheetRow(array $extra = []): array
    {
        return array_merge([
            'emp_number' => 'E-9001',
            'first_name' => 'Sarah',
            'last_name'  => 'Nakato',
            'email'      => 'sarah.nakato@test.local',
        ], $extra);
    }

    public function test_the_importer_puts_a_tin_column_in_tin_number(): void
    {
        $employee = (new EmployeesImport())->model($this->sheetRow(['tin_number' => '1001008394']));

        $this->assertSame('1001008394', $employee->tin_number);
    }

    public function test_a_sheet_still_headed_tax_number_fills_tin_number(): void
    {
        $employee = (new EmployeesImport())->model($this->sheetRow(['tax_number' => '1001008394']));

        $this->assertSame('1001008394', $employee->tin_number, 'the retired header must fold into the live column');
        $this->assertEmpty($employee->tax_number, 'nothing should land in the retired column');
    }

    public function test_a_real_tin_is_not_overwritten_by_the_retired_header(): void
    {
        $employee = (new EmployeesImport())->model($this->sheetRow([
            'tin_number' => '1001008394',
            'tax_number' => '9999999999',
        ]));

        $this->assertSame(
            '1001008394',
            $employee->tin_number,
            'tin_number wins when a sheet carries both'
        );
    }

    public function test_the_create_form_offers_a_tin_field_and_not_the_retired_one(): void
    {
        $form = file_get_contents(resource_path('views/employees/create.blade.php'));

        $this->assertStringContainsString('name="tin_number"', $form);
        $this->assertStringNotContainsString('name="tax_number"', $form);
    }

    public function test_the_edit_form_holds_one_field_for_the_number_not_two(): void
    {
        $form = file_get_contents(resource_path('views/employees/edit.blade.php'));

        $this->assertSame(1, substr_count($form, 'name="tin_number"'));
        $this->assertStringNotContainsString('name="tax_number"', $form);
    }

    public function test_no_employee_record_is_created_without_the_sheet_keys(): void
    {
        $this->assertNull((new EmployeesImport())->model(['first_name' => 'Nobody']));
    }
}
