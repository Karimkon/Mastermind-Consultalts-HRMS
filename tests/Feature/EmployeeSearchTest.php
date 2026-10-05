<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Searching an employee by name has to find them.
 *
 * Four of the five employee searches matched first_name OR last_name against
 * the whole term. Type a full name and you got nothing: "Lastone Juma" is not
 * in first_name on its own and not in last_name on its own. And these names
 * are written in both orders — every payroll schedule from the client says
 * "Juma Lastone" while the record says "Lastone Juma" — so whichever way
 * somebody typed it they stood a fair chance of concluding the person was not
 * in the system and creating them again.
 *
 * There is one scope now. These tests pin the cases that used to fail.
 */
class EmployeeSearchTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $first, string $last, string $number): Employee
    {
        return Employee::create([
            'emp_number' => $number,
            'first_name' => $first,
            'last_name'  => $last,
            'hire_date'  => '2020-01-01',
            'status'     => 'active',
        ]);
    }

    private function found(string $term): array
    {
        return Employee::searchFor($term)->pluck('emp_number')->sort()->values()->all();
    }

    public function test_the_full_name_finds_the_person(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');

        // This is the case that returned nothing.
        $this->assertSame(['RUL014'], $this->found('Lastone Juma'));
    }

    public function test_the_name_the_other_way_round_finds_them_too(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');

        // How the client's payroll schedules write it.
        $this->assertSame(['RUL014'], $this->found('Juma Lastone'));
    }

    public function test_either_half_of_the_name_still_works(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');

        $this->assertSame(['RUL014'], $this->found('Lastone'));
        $this->assertSame(['RUL014'], $this->found('Juma'));
        $this->assertSame(['RUL014'], $this->found('laston'));
    }

    public function test_the_staff_number_still_works(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');

        $this->assertSame(['RUL014'], $this->found('RUL014'));
        $this->assertSame(['RUL014'], $this->found('rul014'));
    }

    public function test_a_middle_name_does_not_hide_somebody(): void
    {
        $this->employee('Geofrey', 'Friday', 'RUL062');

        // The schedule calls him "Friday Geofrey Longole".
        $this->assertSame(['RUL062'], $this->found('Friday Geofrey'));
    }

    public function test_it_does_not_match_people_who_only_share_one_name(): void
    {
        $this->employee('Ratibu', 'Atiku', 'RUL280');
        $this->employee('Geoffrey', 'Atiku', 'RUL281');

        // Both share the surname, so a surname search finds both — but a full
        // name must pick out the one person. Paying the wrong Atiku is the
        // failure this guards.
        $this->assertSame(['RUL280', 'RUL281'], $this->found('Atiku'));
        $this->assertSame(['RUL280'], $this->found('Atiku Ratibu'));
        $this->assertSame(['RUL281'], $this->found('Atiku Geoffrey'));
    }

    public function test_extra_spaces_do_not_break_it(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');

        $this->assertSame(['RUL014'], $this->found('  Lastone   Juma  '));
    }

    public function test_an_empty_search_does_not_filter_anything_out(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');
        $this->employee('Ashiraf', 'Lule', 'RUL002');

        $this->assertCount(2, $this->found(''));
        $this->assertCount(2, $this->found('   '));
    }

    public function test_somebody_who_is_not_there_is_not_found(): void
    {
        $this->employee('Lastone', 'Juma', 'RUL014');

        $this->assertSame([], $this->found('Byarugaba Deus'));
    }

    public function test_the_linked_login_is_searchable_too(): void
    {
        $e = $this->employee('Lastone', 'Juma', 'RUL014');
        $u = User::create([
            'name' => 'Lastone Juma', 'email' => 'lastone.juma@roofings.com', 'password' => bcrypt('x'),
        ]);
        $e->update(['user_id' => $u->id]);

        $this->assertSame(['RUL014'], $this->found('lastone.juma@roofings.com'));
    }
}
