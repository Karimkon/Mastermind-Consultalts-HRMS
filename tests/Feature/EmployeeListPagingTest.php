<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountManagerController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * How many employees a page shows.
 *
 * Paging 439 people twenty-five at a time is eighteen clicks to reach the end of
 * one client, which is why a size selector was asked for. The interesting part is
 * not the sizes on offer but what happens to the ones that are not: a page size
 * arrives in a query string, and `?per_page=100000` against a table of 906 rows
 * is a cheap way to make the server do a lot of work on request.
 */
class EmployeeListPagingTest extends TestCase
{
    use RefreshDatabase;

    private function perPage(?string $requested, int $total = 439): int
    {
        $method = new ReflectionMethod(AccountManagerController::class, 'employeesPerPage');
        $method->setAccessible(true);

        $request = Request::create('/account-manager/employees', 'GET',
            $requested === null ? [] : ['per_page' => $requested]);

        return $method->invoke(app(AccountManagerController::class), $request, $total);
    }

    /**
     * @return array<string, array{string|null, int}>
     */
    public static function sizes(): array
    {
        return [
            'nothing asked for' => [null, 25],
            'the default' => ['25', 25],
            'fifty' => ['50', 50],
            'a hundred' => ['100', 100],
            'two hundred' => ['200', 200],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sizes')]
    public function test_an_offered_size_is_used(?string $requested, int $expected): void
    {
        $this->assertSame($expected, $this->perPage($requested));
    }

    /** "All" means all of them, not a number somebody hopes is big enough. */
    public function test_all_returns_the_whole_set(): void
    {
        $this->assertSame(439, $this->perPage('all', total: 439));
        $this->assertSame(906, $this->perPage('all', total: 906));
    }

    /** The paginator rejects a page size of zero, so an empty set still asks for one. */
    public function test_all_on_an_empty_list_still_asks_for_a_page(): void
    {
        $this->assertSame(1, $this->perPage('all', total: 0));
    }

    /** Even "all" is bounded. A client with 50,000 staff is not a reason to load them. */
    public function test_all_is_capped(): void
    {
        $this->assertSame(2000, $this->perPage('all', total: 50_000));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedSizes(): array
    {
        return [
            'a size nobody offered' => ['37'],
            'an enormous one' => ['100000'],
            'zero' => ['0'],
            'negative' => ['-50'],
            'not a number' => ['everything'],
            'an injection attempt' => ['25; DROP TABLE employees'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedSizes')]
    public function test_anything_else_falls_back_to_the_default(string $requested): void
    {
        $this->assertSame(
            25,
            $this->perPage($requested),
            "A page size of \"{$requested}\" should not be honoured."
        );
    }
}
