<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\OperationKey;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Schemas\Data\SchemaOperationStatus;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use RuntimeException;

final class FieldScenario
{
    public const string WORKER = 'corex.fields-schema';

    public const string ENTITY = 'cas.equipment';

    public const string EXTENSION = 'thirdparty/custom-attribute-source';

    public const string RECORD_RACE = '40000000-0000-7000-8000-000000000031';

    public const string RECORD_BAD = '40000000-0000-7000-8000-000000000032';

    public const string RECORD_HIDDEN = '40000000-0000-7000-8000-000000000033';

    public const string SERIAL = '01900000-0000-7000-8000-000000000001';

    public const string INSPECTION = '01900000-0000-7000-8000-000000000003';

    public static function install(Scenario $proof, string $case): Connection
    {
        $migrations = ScenarioSetup::install($case, false);
        $database = Scenario::connection('a');
        $proof->check($migrations['a'] !== [], 'The actual tenant owner schemas were installed');
        $database->table('users')->insert(['id' => Scenario::USER_B, 'email' => 'restricted@example.test', 'name' => 'Restricted field reader']);
        $database->table('wsp_workspace_members')->insert([
            'id' => (string) Str::uuid7(), 'workspace_id' => Scenario::W1, 'user_id' => Scenario::USER_B, 'status' => 'active',
        ]);
        $database->statement('CREATE TABLE scenario_field_worker_grants (account_id uuid NOT NULL, workspace_id uuid NOT NULL, service text NOT NULL, PRIMARY KEY (account_id, workspace_id, service))');
        $database->table('scenario_field_worker_grants')->insert([
            'account_id' => Scenario::BOXED, 'workspace_id' => Scenario::W1, 'service' => self::WORKER,
        ]);
        self::within(static function (): void {
            app(ModuleLifecycle::class)->enable(self::EXTENSION, cascade: true);
        });
        $database->table('cas_equipment')->insert([
            ['id' => self::RECORD_RACE, 'name' => 'Coincident writer record'],
            ['id' => self::RECORD_BAD, 'name' => 'Historical conversion record'],
            ['id' => self::RECORD_HIDDEN, 'name' => 'Restricted record'],
        ]);
        foreach ([Scenario::USER_A, Scenario::USER_B] as $actor) {
            self::grant($database, $actor, new RecordRef('fields.schema', self::ENTITY), [FieldUse::Read]);
        }
        self::grant($database, Scenario::USER_A, new RecordRef('fields.schema', self::ENTITY), [FieldUse::Write]);
        foreach (['sys_attribute_documents', 'sys_attribute_write_tokens', 'sys_attribute_definitions', 'sys_attribute_key_reservations',
            'sys_attribute_schema_operations', 'sys_attribute_backfills', 'sys_field_schema_heads'] as $table) {
            $proof->check($database->getSchemaBuilder()->hasTable($table), 'The active owner installed '.$table);
        }
        foreach (['sys_attribute_eav_records', 'sys_attribute_eav_values'] as $table) {
            $proof->same(false, $database->getSchemaBuilder()->hasTable($table), 'Clean runtime omits '.$table);
        }

        return $database;
    }

    public static function within(Closure $work, string $actor = Scenario::USER_A): mixed
    {
        return ScenarioQueue::inScope(Scenario::BOXED, Scenario::W1, $actor, static function () use ($work): mixed {
            $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());

            return $work($scope);
        });
    }

    public static function entity(): EntityHandle
    {
        return new EntityHandle(self::ENTITY);
    }

    public static function record(string $id = self::RECORD_RACE): RecordRef
    {
        return new RecordRef(self::ENTITY, $id);
    }

    public static function catalogue(DataScope $scope): FieldSchemaSnapshot
    {
        return app(FieldCatalogue::class)->snapshot($scope, self::entity());
    }

    public static function apply(DataScope $scope, FieldSchemaChange $change, string $key): SchemaOperationRef
    {
        $schemas = app(FieldSchemas::class);
        $plan = $schemas->plan($scope, $change, self::catalogue($scope)->revision);

        return $schemas->apply($scope, $plan->ref, $plan->digest, new OperationKey($key, 1));
    }

    public static function step(): void
    {
        $context = app(TenantContextResolver::class)->current();
        $status = app(ScenarioPrincipalAuthority::class)->forService($context, self::WORKER,
            static fn (): int => Artisan::call('corex:custom-fields:work', ['--limit' => 1]));

        if ($status !== 0) {
            throw new RuntimeException('The installed Fields worker failed. '.Artisan::output());
        }
    }

    /** @param list<string> $states */
    public static function advanceTo(DataScope $scope, SchemaOperationRef $operation, array $states): SchemaOperationStatus
    {
        for ($steps = 0; $steps < 32; $steps++) {
            $status = app(FieldSchemas::class)->inspect($scope, $operation);

            if (in_array($status->state, $states, true)) {
                return $status;
            }

            if (in_array($status->state, ['blocked', 'completed', 'cancelled', 'failed'], true)) {
                break;
            }
            self::step();
            app(ScenarioClock::class)->advance(1);
        }

        throw new RuntimeException('The Fields operation did not reach '.implode(', ', $states).'. Last receipt '.json_encode($status, JSON_THROW_ON_ERROR));
    }

    public static function schemaRow(Connection $database, string $operation): object
    {
        return $database->table('sys_attribute_schema_operations')->selectRaw('*, xmin::text AS tuple_version')->where('id', $operation)->firstOrFail();
    }

    /** @param list<FieldUse> $uses @param list<string> $fields */
    public static function grant(Connection $database, string $actor, RecordRef $resource, array $uses, array $fields = ['record']): void
    {
        $grants = [];
        foreach ($uses as $use) {
            foreach ($fields as $field) {
                $grants[] = [
                    'principal_id' => $actor, 'workspace_id' => Scenario::W1, 'entity' => $resource->entity,
                    'record_id' => $resource->id, 'field_id' => $field, 'field_use' => $use->value,
                ];
            }
        }
        $database->table('scenario_field_grants')->insert($grants);
    }
}
