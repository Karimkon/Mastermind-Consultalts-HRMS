<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;
use Throwable;

/**
 * Every model, against the schema it actually has.
 *
 * This repository had two tests, both Laravel's untouched stubs, and sixty-five
 * models — several of them added in the same week as the tables they sit on. The
 * failure that costs the most in that situation is not a broken feature; it is a
 * model that has quietly drifted from its table. A mistyped column in `$fillable`
 * is silently discarded on create, so the row saves and the value vanishes. A
 * relationship naming a foreign key that no longer exists throws only when some
 * screen finally loads it, months later, in front of somebody.
 *
 * Writing sixty-five test classes by hand would take a week and would rot. This
 * walks the models instead and asks three questions of each, all answerable
 * without a single fixture:
 *
 *   1. Does its table exist?
 *   2. Is every column it claims to fill a real column?
 *   3. Does every relationship it declares produce SQL the database accepts?
 *
 * The third is the valuable one. Building a relation's query and running it forces
 * the database to resolve every column named in the join, so a foreign key that was
 * renamed or never created fails here rather than in production.
 */
class ModelIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Models that cannot be checked this way, each for a stated reason.
     *
     * Kept deliberately short. An entry here is a hole in the coverage, not a
     * convenience, so it needs to earn its place.
     *
     * @var array<string, string>
     */
    private const SKIP = [
        // Spatie's pivot-backed models come from the package's own migrations and
        // are covered by the package's tests, not ours.
    ];

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        $cases = [];

        // A data provider is static and runs before the application is created,
        // so app_path() is not available here.
        foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $cases[basename($file, '.php')] = [$class];
        }

        return $cases;
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function test_the_model_has_a_table(string $class): void
    {
        $model = new $class;
        $table = $model->getTable();

        $this->assertTrue(
            Schema::hasTable($table),
            sprintf('%s reads and writes `%s`, and no migration creates it.', class_basename($class), $table)
        );
    }

    /**
     * A column in `$fillable` that does not exist is not an error anywhere in
     * Laravel: `create()` silently discards it. The row saves, the field is empty,
     * and the bug surfaces as "the system did not keep what I typed".
     *
     * @param  class-string<Model>  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function test_every_fillable_column_exists(string $class): void
    {
        $model = new $class;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("`{$table}` does not exist; covered by the table test.");
        }

        $fillable = $model->getFillable();

        if ($fillable === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $columns = array_map('mb_strtolower', Schema::getColumnListing($table));
        $missing = [];

        foreach ($fillable as $attribute) {
            // A fillable entry may address a JSON path (`meta->flag`); only the
            // column before the arrow has to exist.
            $column = mb_strtolower(Str::before($attribute, '->'));

            if (! in_array($column, $columns, true)) {
                $missing[] = $attribute;
            }
        }

        $this->assertSame(
            [],
            $missing,
            sprintf(
                '%s says it can fill [%s], and `%s` has no such column. Laravel discards these silently on create.',
                class_basename($class),
                implode(', ', $missing),
                $table,
            )
        );
    }

    /**
     * Every relationship, executed against the real schema.
     *
     * The query is run rather than merely built. Building it only assembles a
     * string; running it makes the database resolve every column named in the join,
     * which is the only way a foreign key that was renamed, or never created, will
     * actually say so.
     *
     * @param  class-string<Model>  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function test_every_relationship_resolves_against_the_schema(string $class): void
    {
        $model = new $class;

        if (! Schema::hasTable($model->getTable())) {
            $this->markTestSkipped('No table; covered by the table test.');
        }

        $broken = [];
        $checked = 0;

        foreach ($this->relationshipMethods($class) as $method) {
            try {
                $relation = $model->{$method}();
            } catch (Throwable $e) {
                // Not a relationship after all, or one that needs a loaded parent.
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $checked++;

            try {
                // limit(0) keeps this cheap while still making the database parse
                // and resolve the whole statement.
                $relation->getQuery()->limit(0)->get();
            } catch (Throwable $e) {
                $broken[] = sprintf('%s(): %s', $method, Str::before($e->getMessage(), ' (Connection'));
            }
        }

        $this->assertSame(
            [],
            $broken,
            sprintf("%s has relationships the schema cannot answer:\n  %s",
                class_basename($class), implode("\n  ", $broken))
        );

        $this->addToAssertionCount($checked);
    }

    /**
     * Method names on the model that might be relationships.
     *
     * Where a return type is declared it is trusted, because that is unambiguous.
     * Where it is not — and most of this codebase does not declare one — the name
     * is used as a filter to avoid calling accessors and helpers that could have
     * side effects, and the caller confirms with an instanceof afterwards.
     *
     * @param  class-string<Model>  $class
     * @return array<int, string>
     */
    private function relationshipMethods(string $class): array
    {
        $skip = ['boot', 'booted', 'newFactory', 'getTable', 'toArray', 'jsonSerialize',
            'fresh', 'refresh', 'replicate', 'getKey', 'getRouteKey', 'getQueueableId'];

        $methods = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $class || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            if (in_array($method->getName(), $skip, true) || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $type = $method->getReturnType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                if (is_a($type->getName(), Relation::class, true)) {
                    $methods[] = $method->getName();
                }

                continue;
            }

            if ($type !== null) {
                // Declared as a scalar, array, void — definitely not a relation.
                continue;
            }

            // Undeclared. Accessors and scopes are named for what they return, so
            // the ones worth trying are the plain noun-shaped methods.
            if (! str_starts_with($method->getName(), 'scope')
                && ! str_starts_with($method->getName(), 'get')
                && ! str_starts_with($method->getName(), 'set')
                && ! str_starts_with($method->getName(), 'is')
                && ! str_starts_with($method->getName(), 'has')
                && ! str_starts_with($method->getName(), 'can')) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }
}
