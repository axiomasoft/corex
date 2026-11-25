<?php

declare(strict_types=1);

namespace App\FieldSearch;

use App\Scenario\FieldPgWitness;
use App\Scenario\Scenario;
use App\Scenario\ScenarioSetup;
use App\Scenario\SearchScenarioData;
use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\OperationKey;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\FieldWriteReceipt;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\RebuildInput;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use RuntimeException;

final class EquipmentOwner
{
    public const string ENTITY = 'eq.equipment';

    public const string ASSET = 'rack:Q7/line-021';

    public const string SECOND = 'rack:Q7/line-022';

    public const string SERIAL = '01900000-0000-7000-8000-000000000001';

    public const string PRIORITY = '01900000-0000-7000-8000-000000000097';

    public static function install(Scenario $proof): Connection
    {
        ScenarioSetup::install('field-search', false);
        $db = Scenario::connection('a');
        $db->getSchemaBuilder()->create('scenario_search_service_grants', static function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->uuid('workspace_id');
            $table->string('service');
            $table->primary(['account_id', 'workspace_id', 'service']);
        });
        $db->table('scenario_search_service_grants')->insert(['account_id' => Scenario::BOXED, 'workspace_id' => Scenario::W1, 'service' => SearchScenarioData::SERVICE]);
        $db->getSchemaBuilder()->create('scenario_equipment_locales', static function (Blueprint $table): void {
            $table->string('asset_key');
            $table->string('field_id');
            $table->jsonb('translations');
            $table->primary(['asset_key', 'field_id']);
        });
        Scenario::connection('control')->getSchemaBuilder()->create('scenario_field_search_broker', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('wire');
            $table->char('digest', 64);
            $table->string('quarantine')->nullable();
        });
        self::within(static function () use ($proof): void {
            resolve(ModuleLifecycle::class)->enable('equipment/registry', cascade: true);
            $class = resolve(EntityRegistry::class)->get(self::ENTITY)->model;
            $model = new $class;
            $proof->check($model instanceof Model && $model::class === 'Equipment\\Registry\\Equipment' && $model->getKeyName() === 'asset_key', 'The real installed Equipment model declares its custom primary key');
            $model->newQuery()->forceCreate(['asset_key' => self::ASSET, 'name' => 'Registry Pump']);
            $model->newQuery()->forceCreate(['asset_key' => self::SECOND, 'name' => 'Control Valve']);
        });
        $proof->same(false, $db->getSchemaBuilder()->hasColumn('eq_equipment', 'id'), 'The installed owner table has no conventional id fallback');
        foreach ([
            [self::ASSET, EquipmentLocaleSource::PUBLIC_ID, ['en' => 'Registry Pump', 'ru-RU' => 'Насос']],
            [self::SECOND, EquipmentLocaleSource::PUBLIC_ID, ['en' => 'Control Valve', 'zh-Hant-TW' => '控制閥']],
            [self::ASSET, EquipmentLocaleSource::PRIVATE_ID, ['en' => 'sealed-valve']],
            [self::SECOND, EquipmentLocaleSource::PRIVATE_ID, ['en' => 'sealed-other']],
        ] as [$record, $field, $translations]) {
            $db->table('scenario_equipment_locales')->insert([
                'asset_key' => $record, 'field_id' => $field, 'translations' => json_encode($translations, JSON_THROW_ON_ERROR),
            ]);
        }

        return $db;
    }

    public static function entity(): EntityHandle
    {
        return new EntityHandle(self::ENTITY);
    }

    public static function record(string $id = self::ASSET): RecordRef
    {
        return new RecordRef(self::ENTITY, $id);
    }

    public static function records(DataScope $scope): Builder
    {
        $db = DB::connection();
        resolve(CurrentWorkScope::class)->assert($scope, $db);

        if ($scope->accountId !== Scenario::BOXED || $scope->workspaceId !== Scenario::W1) {
            throw new RuntimeException('The equipment owner does not own this logical scope.');
        }

        return $db->table('eq_equipment')->whereNull('deleted_at');
    }

    public static function within(Closure $work, bool $service = false): mixed
    {
        return SearchScenarioData::within($work, service: $service);
    }

    public static function fieldChange(bool $revised): string
    {
        $id = self::within(static function (DataScope $scope) use ($revised) {
            $field = new FieldDefinition(new FieldId(self::PRIORITY), self::entity(), 'priority', new FieldTypeId($revised ? 'int' : 'text'),
                'runtime', revision: $revised ? 2 : 1, uses: [FieldUse::Read, FieldUse::Write, FieldUse::Filter]);
            $change = $revised ? FieldSchemaChange::revise($field, 'equipment.priority-integer', 1) : FieldSchemaChange::add($field);
            $schemas = resolve(FieldSchemas::class);
            $snapshot = resolve(FieldCatalogue::class)->snapshot($scope, self::entity());
            $plan = $schemas->plan($scope, $change, $snapshot->revision);

            return $schemas->apply($scope, $plan->ref, $plan->digest, new OperationKey($revised ? 'change-priority-type' : 'add-priority', 1));
        });

        return FieldPgWitness::await('the installed schema worker publishes the effective equipment schema', static function () use ($id): ?string {
            $status = self::within(static fn (DataScope $scope) => resolve(FieldSchemas::class)->inspect($scope, $id));

            if ($status->state === 'completed') {
                return $id->id;
            }
            self::within(static function (): void {
                if (Artisan::call('corex:custom-fields:work', ['--limit' => 1]) !== 0) {
                    throw new RuntimeException(Artisan::output());
                }
            }, service: true);

            return null;
        });
    }

    public static function update(DataScope $scope, array $values, string $key): FieldWriteReceipt
    {
        $fields = resolve(FieldValues::class);
        $record = self::record();
        $prior = $fields->read($scope, $record, new FieldSelection([]));
        $ops = [];
        foreach ($values as $id => $value) {
            $ops[] = new SetField(new FieldId($id), FieldInput::from($value));
        }
        $patch = new FieldPatch($ops, $prior->schemaRevision, $prior->valueRevision);

        return $fields->update($scope, $record, $patch, $patch->token($scope, $record, $key));
    }

    public static function relay(): string
    {
        if (Artisan::call('corex:messaging:relay', ['--once' => true, '--batch' => 100]) !== 0) {
            throw new RuntimeException(Artisan::output());
        }

        return Artisan::output();
    }

    public static function rebuild(DataScope $scope, string $key): IndexTarget
    {
        $rebuilds = resolve(SearchRebuilds::class);
        $plan = $rebuilds->plan($scope, new RebuildInput(self::entity()));
        $ref = $rebuilds->start($scope, $plan->ref, new OperationKey($key, 1));
        $status = $rebuilds->inspect($scope, $ref);
        $generation = $status->destination;
        FieldPgWitness::await('the real equipment scan finishes', static function () use ($scope, $rebuilds, $ref, $generation): ?bool {
            if ($rebuilds->inspect($scope, $ref)->state === 'catching-up') {
                return true;
            }
            resolve(IndexWorker::class)->advance($scope, self::entity(), $generation);

            return null;
        });
        $worker = resolve(RebuildWorker::class);
        $barrier = $worker->barrier($scope, $ref);
        self::confirm($scope, $generation, $barrier->barrier ?? throw new RuntimeException('Missing equipment H.'));

        if ($worker->publish($scope, $ref)->state !== 'published') {
            throw new RuntimeException('The real worker failed to publish the equipment generation.');
        }

        return self::target($scope, $generation);
    }

    public static function lane(Connection $db): object
    {
        return $db->table('corex_search_lanes')->where(['entity' => self::ENTITY, 'workspace_scope' => 'workspace:'.Scenario::W1,
            'backend' => 'meilisearch', 'cluster' => getenv('COREX_SCENARIO_MEILI_NAMESPACE')])->sole();
    }

    public static function confirm(DataScope $scope, int $generation, int $seq): object
    {
        return FieldPgWitness::await('actual Meilisearch confirms the equipment watermark', static function () use ($scope, $generation, $seq): ?object {
            $db = Scenario::connection('a');
            $row = $db->table('corex_search_generations')->where(['lane_key' => self::lane($db)->lane_key, 'generation' => $generation])->sole();

            if ((int) $row->confirmed_seq >= $seq) {
                return $row;
            }
            resolve(IndexWorker::class)->advance($scope, self::entity(), $generation);

            return null;
        });
    }

    public static function target(DataScope $scope, int $generation): IndexTarget
    {
        $db = Scenario::connection('a');
        $row = $db->table('corex_search_generations')->where(['lane_key' => self::lane($db)->lane_key, 'generation' => $generation])->sole();

        return new IndexTarget($scope, self::entity(), 'meilisearch', $row->cluster, $generation, $row->uid, $row->mapping_digest, $row->allocation_id);
    }
}
