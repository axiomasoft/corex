<?php

declare(strict_types=1);

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldMetadata;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Contracts\FieldUseSnapshot;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Laravel\JsonDocuments;
use Illuminate\Support\Facades\DB;

uses(SchemaPgTestCase::class)->group('pg');

beforeEach(function (): void {
    DB::statement('CREATE TABLE metadata_test_policy (allowed boolean NOT NULL, epoch integer NOT NULL)');
    DB::table('metadata_test_policy')->insert(['allowed' => true, 'epoch' => 1]);
    config(['database.connections.metadata_mutator' => config('database.connections.pgsql')]);
    $this->mutator = DB::connection('metadata_mutator');
    expect($this->mutator->selectOne('select pg_backend_pid() as pid')->pid)
        ->not->toBe(DB::selectOne('select pg_backend_pid() as pid')->pid);
    $this->policy = new FieldMetadataTestPolicy;
    $this->app->instance(FieldUsePolicy::class, $this->policy);
    $this->app->forgetInstance(FieldCatalogue::class);
});

afterEach(function (): void {
    DB::purge('metadata_mutator');
});

it('denies metadata when a second session revokes entity admission during the catalogue scan', function (): void {
    $metadata = $this->app->make(FieldMetadata::class);
    $before = $metadata->describe($this->scope, $this->entity);
    expect(array_column(JsonDocuments::map($before->body)['fields'], 'id'))->toBe([$this->declared->id->value]);
    $this->policy->onField = fn (): int => $this->mutator->table('metadata_test_policy')->update(['allowed' => false]);

    expect(fn () => $metadata->describe($this->scope, $this->entity))
        ->toThrow(fn (FieldFailure $error) => expect($error->errorDescriptor()->errorCode)->toBe(CustomFieldsErrorCode::AccessDenied));
});

it('uses only current policy witnesses after another session changes the epoch during enumeration', function (): void {
    $metadata = $this->app->make(FieldMetadata::class);
    $before = $metadata->describe($this->scope, $this->entity);
    $this->policy->onField = fn (): int => $this->mutator->table('metadata_test_policy')->update(['epoch' => 2]);

    $after = $metadata->describe($this->scope, $this->entity);
    $fresh = $metadata->describe($this->scope, $this->entity);
    expect($before->body->canonicalJson)->toBe($after->body->canonicalJson)
        ->and($before->witness)->not->toBe($after->witness)
        ->and($after->witness)->toBe($fresh->witness);
});

it('releases the current owner schema when publication follows the initial catalogue snapshot', function (): void {
    $new = $this->field('priority', 'text');
    $advance = function () use ($new): void {
        expect($this->drain($this->admit(FieldSchemaChange::add($new)))->state)->toBe('completed');
    };
    $this->app->instance(FieldCatalogue::class, new class($this->catalogue, $advance) implements FieldCatalogue
    {
        public function __construct(private readonly FieldCatalogue $catalogue, private ?Closure $advance) {}

        public function snapshot(DataScope $scope, EntityHandle $entity): FieldSchemaSnapshot
        {
            $captured = $this->catalogue->snapshot($scope, $entity);
            $advance = $this->advance;
            $this->advance = null;
            $advance?->__invoke();

            return $captured;
        }
    });

    $view = JsonDocuments::map($this->app->make(FieldMetadata::class)->describe($this->scope, $this->entity)->body);
    expect(array_column($view['fields'], 'id'))->toContain($new->id->value)
        ->and($view['schema_revision'])->toBe($this->catalogue->snapshot($this->scope, $this->entity)->revision->value);
});

it('closes metadata for a policy without a current owner snapshot', function (): void {
    $this->app->instance(FieldUsePolicy::class, new class implements FieldUsePolicy
    {
        public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
        {
            return FieldUsePlan::unconditional('metadata-policy');
        }
    });

    expect(fn () => $this->app->make(FieldMetadata::class)->describe($this->scope, $this->entity))
        ->toThrow(fn (FieldFailure $error) => expect($error->errorDescriptor()->errorCode)->toBe(CustomFieldsErrorCode::AccessDenied));
});

final class FieldMetadataTestPolicy implements FieldUseSnapshot
{
    public ?Closure $onField = null;

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $state = DB::table('metadata_test_policy')->first();

        if ($request->fields->fields === []) {
            return $state->allowed ? FieldUsePlan::unconditional('acl-'.$state->epoch) : FieldUsePlan::denied();
        }

        $hook = $this->onField;
        $this->onField = null;
        $hook?->__invoke();

        return FieldUsePlan::unconditional('acl-'.$state->epoch);
    }

    public function inSnapshot(DataScope $scope, EntityHandle $entity, Closure $read): mixed
    {
        return DB::transaction(static function () use ($read): mixed {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            return $read();
        });
    }
}
