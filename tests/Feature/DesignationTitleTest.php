<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A job title is stored in `designations.title`.
 *
 * Five places read `designation->name`, which is not a column. Eloquent returns
 * null for a missing attribute rather than raising, so the web list rendered an
 * empty line and the app returned a dash — and nobody noticed, because almost
 * no employee had a designation set in the first place. The moment head office
 * got job titles, every one of them would have displayed blank.
 */
class DesignationTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_column_is_title_and_there_is_no_name_column(): void
    {
        $this->assertTrue(Schema::hasColumn('designations', 'title'));
        $this->assertFalse(Schema::hasColumn('designations', 'name'),
            'If a name column is ever added, the readers below need revisiting.');
    }

    public function test_a_designation_reached_through_an_employee_has_a_readable_title(): void
    {
        $designation = Designation::create(['title' => 'Account Manager']);

        $employee = Employee::create([
            'emp_number' => 'HQ008',
            'first_name' => 'Violet',
            'last_name' => 'Namuleme',
            'hire_date' => '2024-01-15',
            'status' => 'active',
            'designation_id' => $designation->id,
        ]);

        $this->assertSame('Account Manager', $employee->fresh()->designation->title);
        $this->assertNull($employee->fresh()->designation->name,
            'This is exactly what the five broken readers were getting.');
    }

    /** The readers themselves, so a regression shows up here rather than on screen. */
    public function test_no_code_reads_designation_name(): void
    {
        $roots = [
            dirname(__DIR__, 2).'/app',
            dirname(__DIR__, 2).'/resources/views',
        ];

        $offenders = [];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                    continue;
                }

                $body = file_get_contents($file->getPathname());

                if (preg_match('/designation\??->name\b/', $body)) {
                    $offenders[] = str_replace(dirname(__DIR__, 2), '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders,
            "These read designations.name, which does not exist:\n  ".implode("\n  ", $offenders));
    }
}
