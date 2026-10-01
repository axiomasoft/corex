<?php

declare(strict_types=1);

namespace ThirdParty\Transfer;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Database\Laravel\BaseModel;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\FieldDefinition;
use CoreX\Modules\Data\InstalledPackageVersion;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Transfer\Contracts\RecordMapper;
use CoreX\Transfer\Contracts\TransferSink;
use CoreX\Transfer\Contracts\TransferSource;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\ConflictRule;
use CoreX\Transfer\Data\ImportDecision;
use CoreX\Transfer\Data\MappingResult;
use CoreX\Transfer\Data\MappingSnapshot;
use CoreX\Transfer\Data\RowBatch;
use CoreX\Transfer\Data\SourceCursor;
use CoreX\Transfer\Data\SourceManifest;
use CoreX\Transfer\Data\SourceRow;
use CoreX\Transfer\Data\TargetResult;
use CoreX\Transfer\Data\TransferLimits;
use RuntimeException;

final class Module
{
    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make('thirdparty/custom-transfer-codec')
            ->title('Custom transfer codec')
            ->description('Checkpointed equipment import with one typed row failure.')
            ->tablePrefix('ctc')
            ->extends([
                Entity::make(BaseModel::class)->handle('ctc.equipment')->table('ctc_equipment')->fields([new FieldDefinition('name', 'string')]),
                Entity::make(BaseModel::class)->handle('ctc.batch')->table('ctc_batches')->fields([new FieldDefinition('name', 'string')]),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion('thirdparty/custom-transfer-codec', '1.0.0', 'custom-transfer-codec');
    }

    public static function prove(): void
    {
        $hash = hash('sha256', 'equipment-source');
        $rows = [
            new SourceRow('0', ['serial' => 'pump-1'], $hash),
            new SourceRow('1', ['note' => 'missing serial'], $hash),
        ];
        $source = new CheckpointSource($rows, $hash);
        $codec = new EquipmentCodec;
        $sink = new CheckpointSink;
        $scope = new DataScope('account-1', null, 'sqlite-memory', 'actor-1', hash('sha256', 'transfer-epoch'));
        $limits = new TransferLimits;
        $mapping = new MappingSnapshot('map/1', hash('sha256', 'mapping'), ['serial' => 'serial'], hash('sha256', 'mapping-body'));
        $cursor = new SourceCursor('0', null, $hash);
        $applied = [];

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
                $applied[] = $sink->apply($scope, $record, new ConflictRule('create-only'), $token)->status;
            }
        }

        $resume = $source->read($source->describe($scope), new SourceCursor('1', null, $hash), $limits);

        if ($applied !== ['applied'] || count($sink->applied()) !== 1 || count($resume->rows) !== 1 || $resume->rows[0]->ordinal !== '1') {
            throw new RuntimeException('Checkpointed transfer did not keep the applied row and the typed failure apart.');
        }

        if ($codec->map($rows[1], $mapping)->errorCode !== 'missing-serial') {
            throw new RuntimeException('Typed row failure did not name missing-serial.');
        }
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

    /** @return array<string, TargetResult> */
    public function applied(): array
    {
        return $this->results;
    }
}
