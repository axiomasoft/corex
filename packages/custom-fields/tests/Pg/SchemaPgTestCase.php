<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Pg;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Schemas\Data\SchemaOperationStatus;
use CoreX\CustomFields\Schemas\Internal\DeclaredSchema;
use CoreX\CustomFields\Schemas\Internal\SchemaRows;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\FieldWriteReceipt;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

abstract class SchemaPgTestCase extends PgTestCase
{
    protected DataScope $scope;

    protected EntityHandle $entity;

    protected FieldDefinition $declared;

    protected FieldSchemas $schemas;

    protected FieldCatalogue $catalogue;

    protected SchemaTestClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new SchemaTestClock;
        $this->app->instance(ClockInterface::class, $this->clock);
        $scope = jsonbScope();
        $this->scope = new DataScope($scope->deploymentId, $scope->accountId, $scope->workspaceId, $scope->bindingId, $scope->bindingGeneration,
            $scope->accountIncarnation, $scope->restoreEpoch, 'service', 'schema-test');
        $this->entity = new EntityHandle('cas.equipment');
        $this->declared = $this->field('serial', 'text', 'vendor/fixture');
        $this->app->instance(TenantContextResolver::class, new class implements TenantContextResolver
        {
            public function current(): TenantContext
            {
                return jsonbContext();
            }
        });
        CallerWorkScopeFixture::bind($this->app, $this->scope);
        $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer());
        $this->app->instance(FieldUsePolicy::class, new class implements FieldUsePolicy
        {
            public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
            {
                return FieldUsePlan::unconditional('schema-tests');
            }
        });
        $this->app->instance(AccountConnectionResolver::class, new readonly class($this->scope) implements AccountConnectionResolver
        {
            public function __construct(private DataScope $scope) {}

            public function resolve(AccountRef $account): DatabaseConnectionConfig
            {
                $db = DB::connection();

                return new DatabaseConnectionConfig('pgsql', $db->getConfig('host'), (int) $db->getConfig('port'), $db->getDatabaseName(), $db->getConfig('username'),
                    [...SchemaRows::fence($this->scope), 'corex_fields_ddl_transport' => 'direct-v1', 'corex_fields_ddl_namespace' => 'public']);
            }
        });
        $declared = new readonly class($this->declared) implements DeclaredFields
        {
            public function __construct(private FieldDefinition $field) {}

            public function owner(): string
            {
                return $this->field->owner;
            }

            public function fields(EntityHandle $entity): array
            {
                return $entity->value === $this->field->entity->value ? [$this->field] : [];
            }
        };
        $this->app->instance(DeclaredSchema::class, new DeclaredSchema($this->app->make(FieldTypes::class), static fn (): array => [$declared], null, null));
        $this->schemas = $this->app->make(FieldSchemas::class);
        $this->catalogue = $this->app->make(FieldCatalogue::class);
    }

    protected function field(string $key, string $type, string $owner = 'runtime', string $index = 'none', ?FieldId $id = null, array $constraints = []): FieldDefinition
    {
        return new FieldDefinition($id ?? new FieldId((string) Str::uuid7()), $this->entity, $key, new FieldTypeId($type), $owner, constraints: $constraints,
            uses: [FieldUse::Read, FieldUse::Write, FieldUse::Filter], indexPolicy: $index);
    }

    protected function admit(FieldSchemaChange $change): SchemaOperationRef
    {
        $plan = $this->schemas->plan($this->scope, $change, $this->catalogue->snapshot($this->scope, $this->entity)->revision);

        return $this->schemas->apply($this->scope, $plan->ref, $plan->digest, new OperationKey((string) Str::uuid7(), 1));
    }

    protected function writeValue(FieldDefinition $field, string $id, string|int $input, ?DocumentRevision $expected = null): FieldWriteReceipt
    {
        $record = new RecordRef($this->entity->value, $id);
        $schema = $this->catalogue->snapshot($this->scope, $this->entity)->revision;
        $patch = new FieldPatch([new SetField($field->id, FieldInput::from($input))], $schema, $expected ?? DocumentRevision::absent());

        return $this->app->make(FieldValues::class)->update($this->scope, $record, $patch, $patch->token($this->scope, $record, (string) Str::uuid7()));
    }

    protected function step(): void
    {
        self::assertSame(0, Artisan::call('corex:custom-fields:work', ['--limit' => 1]));
    }

    protected function drain(SchemaOperationRef $operation): SchemaOperationStatus
    {
        for ($i = 0; $i < 15; $i++) {
            $status = $this->schemas->inspect($this->scope, $operation);

            if (in_array($status->state, ['blocked', 'failed', 'completed', 'cancelled'], true)) {
                return $status;
            }
            $this->step();
        }
        self::fail('Schema worker did not reach an inspectable stop.');
    }
}
