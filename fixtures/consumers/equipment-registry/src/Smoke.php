<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Database\Laravel\BaseModel;
use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\ImportDecision;
use CoreX\ImportExport\Data\MappingResult;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\RowBatch;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\SourceManifest;
use CoreX\ImportExport\Data\SourceRow;
use CoreX\ImportExport\Data\TargetResult;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\Search\Contracts\IndexDriver;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\SearchQuery;
use Packman\Modules\Data\Compiler\ExtensionStamp;
use Packman\Modules\Data\Compiler\InstalledPackageVersion;
use Packman\Modules\Data\Entities\Entity;
use Packman\Modules\Data\Entities\FieldDefinition;
use Packman\Modules\Data\Extenders\Workflow;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Testing\ExtensionContract;
use PDO;
use RuntimeException;

final class MaintenanceActivity {}

final class Smoke
{
    private static ?SearchProvider $bound = null;

    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make('equipment/registry')
            ->title('Equipment registry')
            ->description('Tracks equipment, inspection attributes and maintenance work.')
            ->tablePrefix('eq')
            ->extends([
                Entity::make(BaseModel::class)->handle('eq.equipment')->table('eq_equipment')->workflowable()->fields([new FieldDefinition('name', 'string')]),
                Workflow::stepType(MaintenanceActivity::class),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion('equipment/registry', '1.0.0', 'equipment-registry');
    }

    public static function driver(): MemoryDriver
    {
        return new MemoryDriver;
    }

    public static function bind(SearchProvider $provider): void
    {
        self::$bound = $provider;
    }

    public static function prove(): void
    {
        $result = self::exercise(self::$bound);

        if ($result['attribute'] !== 'serial' || $result['found'] !== 'eq-1' || $result['imported'] !== 1 || $result['failed'] !== 'missing-serial') {
            throw new RuntimeException('Equipment behavior flow did not resolve the attribute, the index, and the partial import.');
        }
    }

    /** @return array{serial: string, attribute: string, found: string, imported: int, failed: string, maintenance: string} */
    public static function exercise(?SearchProvider $provider): array
    {
        $manifest = self::manifest();
        $reversed = self::manifest();
        $reversed->extenders = array_reverse($reversed->extenders);
        $fingerprint = hash('sha256', 'equipment-registry');
        $recorded = new ExtensionStamp('workflow.step', MaintenanceActivity::class, 'maintenance/1', $fingerprint);
        $otherRegistry = new ExtensionStamp('workflow.step', MaintenanceActivity::class, 'maintenance/1', hash('sha256', 'other-registry'));
        $mismatch = new ExtensionStamp('workflow.step', MaintenanceActivity::class, 'maintenance/2', $fingerprint);

        if (self::stepIds($manifest) !== self::stepIds($reversed) || ! ExtensionContract::sameImplementation($recorded, $otherRegistry) || ExtensionContract::sameImplementation($recorded, $mismatch)) {
            throw new RuntimeException('Maintenance activity identity was not stable.');
        }

        $definitions = self::attributes();
        $provider = $provider instanceof IndexDriver ? $provider : new MemoryDriver;
        $scope = new DataScope(deploymentId: 'fixture-deployment', accountId: 'account-1', workspaceId: null, bindingId: 'fixture-binding', bindingGeneration: 1, accountIncarnation: 'fixture-incarnation', restoreEpoch: hash('sha256', 'equipment-epoch'), principalKind: 'user', principalId: 'actor-1');
        $submission = $provider->submit(new IndexBatch('generation-1', [
            ['id' => 'eq-1', 'entity' => 'equipment', 'text' => 'pump alpha'],
        ]));

        if ($provider->status($submission)->status !== 'ready') {
            throw new RuntimeException('Index submission was not ready.');
        }

        $page = $provider->query($scope, new SearchQuery('equipment', hash('sha256', 'schema'), 'pump'));

        if (count($page->candidates) !== 1 || $page->candidates[0]->record->id !== 'eq-1') {
            throw new RuntimeException('Indexed equipment was not found.');
        }

        $imported = self::import($scope);

        return [
            'serial' => 'pump-1',
            'attribute' => $definitions[0]->key,
            'found' => $page->candidates[0]->record->id,
            'imported' => $imported['applied'],
            'failed' => $imported['failed'],
            'maintenance' => 'scheduled',
        ];
    }

    public static function schedule(string $database): void
    {
        $result = self::exercise(self::$bound ?? new MemoryDriver);
        $pdo = self::database($database);
        $pdo->exec('create table if not exists equipment (id text primary key, serial text not null)');
        $pdo->exec('create table if not exists maintenance_jobs (id integer primary key, serial text not null, status text not null, result text)');
        $pdo->prepare('insert into equipment (id, serial) values (?, ?)')->execute(['eq-1', $result['serial']]);
        $pdo->prepare('insert into maintenance_jobs (serial, status, result) values (?, ?, ?)')->execute([
            $result['serial'],
            'scheduled',
            json_encode($result, JSON_THROW_ON_ERROR),
        ]);
    }

    public static function work(string $database): void
    {
        $pdo = self::database($database);
        $job = $pdo->query('select id, result from maintenance_jobs where status = \'scheduled\' order by id limit 1')->fetch();

        if (! is_array($job)) {
            throw new RuntimeException('No scheduled maintenance job was waiting after the worker restart.');
        }

        $result = json_decode((string) $job['result'], true, flags: JSON_THROW_ON_ERROR);
        $result['maintenance'] = 'completed';
        $statement = $pdo->prepare('update maintenance_jobs set status = ?, result = ? where id = ?');
        $statement->execute(['completed', json_encode($result, JSON_THROW_ON_ERROR), $job['id']]);
    }

    /** @return array<string, mixed> */
    public static function read(string $database): array
    {
        $pdo = self::database($database);
        $result = $pdo->query('select result from maintenance_jobs where status = \'completed\' order by id limit 1')->fetchColumn();

        if (! is_string($result)) {
            throw new RuntimeException('Completed maintenance result is missing.');
        }

        $decoded = json_decode($result, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || $decoded['maintenance'] !== 'completed') {
            throw new RuntimeException('Worker restart did not leave a completed maintenance result.');
        }

        return $decoded;
    }

    /** @return list<AttributeDefinition> */
    private static function attributes(): array
    {
        $rows = [
            ['id' => '01900000-0000-7000-8000-000000000001', 'key' => 'serial', 'type' => 'string'],
            ['id' => '01900000-0000-7000-8000-000000000002', 'key' => 'hours', 'type' => 'integer'],
        ];
        $definitions = [];

        foreach ($rows as $row) {
            $definitions[] = new AttributeDefinition(
                id: AttributeId::v7($row['id']),
                entity: 'equipment',
                key: $row['key'],
                revision: 1,
                type: $row['type'],
                lifecycle: AttributeLifecycle::Active,
                required: true,
                searchable: false,
                constraints: [],
                readKeys: ['inspect'],
                writeKeys: ['inspect'],
                index: IndexPolicy::None,
            );
        }

        $canonical = AttributeDefinition::canonicalList($definitions);
        $reversed = AttributeDefinition::canonicalList(array_reverse($definitions));

        if (AttributeDefinition::schemaHash('equipment', $canonical) !== AttributeDefinition::schemaHash('equipment', $reversed)) {
            throw new RuntimeException('Attribute compilation changed when the scalar source order was reversed.');
        }

        return $canonical;
    }

    /** @return array{applied: int, failed: string} */
    private static function import(DataScope $scope): array
    {
        $hash = hash('sha256', 'equipment-source');
        $rows = [
            new SourceRow('0', ['serial' => 'pump-1'], $hash),
            new SourceRow('1', ['note' => 'missing serial'], $hash),
        ];
        $source = new CheckpointSource($rows, $hash);
        $codec = new EquipmentCodec;
        $sink = new CheckpointSink;
        $limits = new TransferLimits;
        $mapping = new MappingSnapshot('map/1', hash('sha256', 'mapping'), ['serial' => 'serial'], hash('sha256', 'mapping-body'));
        $cursor = new SourceCursor('0', null, $hash);
        $applied = 0;

        for ($step = 0; $step < 2; $step++) {
            $batch = $source->read($source->describe($scope), $cursor, $limits);
            $cursor = $batch->next;

            foreach ($batch->rows as $row) {
                $mapped = $codec->map($row, $mapping);

                if ($mapped->status === 'invalid') {
                    continue;
                }

                $record = $mapped->record ?? throw new RuntimeException('Mapped row has no canonical record.');
                $token = new WriteToken('import-'.$row->ordinal, $record->payloadHash, '0');

                if ($sink->apply($scope, $record, new ConflictRule('create-only'), $token)->status === 'applied') {
                    $applied++;
                }
            }
        }

        $failure = $codec->map($rows[1], $mapping)->errorCode;

        if ($applied !== 1 || $failure !== 'missing-serial') {
            throw new RuntimeException('Partial import did not keep one applied row and one typed failure.');
        }

        return ['applied' => $applied, 'failed' => $failure];
    }

    /** @return list<string> */
    private static function stepIds(ModuleManifest $manifest): array
    {
        $ids = [];

        foreach ($manifest->extenders as $extender) {
            if ($extender instanceof Workflow && $extender->kind === 'stepType' && is_string($extender->stepClass)) {
                $ids[] = $extender->stepClass;
            }
        }

        sort($ids);

        return $ids;
    }

    private static function database(string $path): PDO
    {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}

final class CheckpointSource implements TransferSource
{
    /** @param  list<SourceRow>  $rows */
    public function __construct(private array $rows, private string $hash) {}

    public function describe(DataScope $scope): SourceManifest
    {
        return new SourceManifest('equipment-import', $this->hash, 2, 'csv', 'codec/1', 'UTF-8', ',', '"', '\\', hash('sha256', 'schema'));
    }

    public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
    {
        $start = (int) $after->ordinal;
        $slice = array_slice($this->rows, $start, 1);
        $next = (string) ($start + count($slice));

        return new RowBatch($slice, new SourceCursor($next, null, $this->hash), $next < count($this->rows));
    }
}

final class EquipmentCodec implements RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
    {
        $serial = $row->values['serial'] ?? null;

        if (! is_string($serial) || $serial === '') {
            return new MappingResult('invalid', null, 'missing-serial');
        }

        $record = new CanonicalRecord('equipment', 'fixture', $serial, 'rev-1', null, ['serial' => $serial], hash('sha256', $serial));

        return new MappingResult('mapped', $record, null);
    }
}

final class CheckpointSink implements TransferSink
{
    /** @var array<string, TargetResult> */
    private array $results = [];

    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
    {
        return new ImportDecision('apply', null);
    }

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
    {
        $existing = $this->results[$token->operationKey] ?? null;

        if ($existing instanceof TargetResult) {
            return $existing;
        }

        $result = new TargetResult('applied', new RecordRef($record->entity, $record->externalId), '1', null, null);
        $this->results[$token->operationKey] = $result;

        return $result;
    }

    public function lookup(DataScope $scope, WriteToken $token): TargetResult
    {
        return $this->results[$token->operationKey] ?? new TargetResult('unknown', null, null, null, 'missing-token');
    }
}
