<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexDocument;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Internal\Value;
use CoreX\Search\Projection\Data\ProjectedValues;
use LogicException;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class Submission
{
    public const string OWNER = 'corex.search.index';

    public function __construct(public IndexBatch $batch, public stdClass $row) {}

    /** @return list<array{id:string,values:array<string,string|int|bool|null>}> */
    public static function documents(IndexBatch $batch): array
    {
        return array_map(static fn (IndexDocument $d): array => ['id' => $d->record->id, 'values' => $d->values->values], $batch->documents);
    }

    public static function digest(IndexBatch $batch): string
    {
        $t = $batch->target;

        return hash('sha256', json_encode([$t->cluster, $t->uid, $t->entity->value, $t->mappingDigest,
            $batch->submissionId, $batch->action->value, self::documents($batch)], JSON_THROW_ON_ERROR));
    }

    public static function restore(IndexTarget $target, stdClass $row): self
    {
        $raw = json_decode(Value::text($row->documents), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new LogicException('Invalid stored index submission.');
        }
        $documents = [];
        foreach ($raw as $entry) {
            $entry = Value::map($entry);
            $values = Value::map($entry['values']);
            $checked = [];
            foreach ($values as $key => $value) {
                if (! is_string($value) && ! is_bool($value) && ! is_int($value) && $value !== null) {
                    throw new LogicException('Invalid stored index field.');
                }
                $checked[$key] = $value;
            }
            $documents[] = new IndexDocument(record: new RecordRef(entity: $target->entity->value, id: Value::text($entry['id'])), values: new ProjectedValues($checked));
        }
        $batch = new IndexBatch(target: $target, submissionId: Value::text($row->submission_id), action: IndexAction::from(Value::text($row->action)), documents: $documents);

        if ($row->batch_digest !== self::digest($batch) || $row->uid !== $target->uid || $row->deployment_id !== $target->scope->deploymentId
            || $row->binding_id !== $target->scope->bindingId || Value::integer($row->binding_generation) !== $target->scope->bindingGeneration
            || $row->restore_epoch !== $target->scope->restoreEpoch) {
            throw new LogicException('Index submission differs from its immutable namespace.');
        }

        return new self(batch: $batch, row: $row);
    }

    public function effect(): ProviderEffectRef
    {
        return self::effectFor($this->batch);
    }

    public static function effectFor(IndexBatch $batch): ProviderEffectRef
    {
        $t = $batch->target;

        return new ProviderEffectRef(accountId: $t->scope->accountId, accountIncarnation: $t->scope->accountIncarnation, workspaceId: $t->scope->workspaceId,
            owner: self::OWNER, providerNamespace: hash('sha256', $t->backend.'|'.$t->cluster.'|'.$t->uid), effectKey: $batch->submissionId);
    }
}
