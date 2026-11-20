<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Schemas\Data\ClaimGeneration;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Schemas\Data\SchemaOperationStatus;
use CoreX\Data\Data\DataScope;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;

#[Internal]
final readonly class SchemaJournal
{
    public const string OWNER = 'corex.custom-fields.schema';

    public const int LEASE_SECONDS = 30;

    public function __construct(private ClockInterface $clock) {}

    public function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    public function operation(ConnectionInterface $db, DataScope $scope, string $id): Builder
    {
        return $db->table('sys_attribute_schema_operations')->where(SchemaRows::logical($scope))->where(SchemaRows::fence($scope))->where('id', $id);
    }

    public function claim(ConnectionInterface $db, DataScope $scope, stdClass $claim): Builder
    {
        return $db->table('sys_attribute_schema_operations')->where(SchemaRows::logical($scope))->where(SchemaRows::fence($scope))
            ->where('id', $claim->id)->where('entity', $claim->entity)->where('state', $claim->state)
            ->where('phase', $claim->phase)->where('plan_digest', $claim->plan_digest)->where('claim_token', $claim->claim_token)
            ->where('generation', $claim->generation)->where('lease_until', '>', $this->now());
    }

    public function locked(ConnectionInterface $db, DataScope $scope, stdClass $claim): stdClass
    {
        $row = $this->claim($db, $scope, $claim)->lockForUpdate()->first();

        if ($row === null || new DateTimeImmutable(SchemaValue::text($row->lease_until)) <= $this->clock->now()) {
            throw new SchemaClaimLost('Schema claim expired.');
        }

        return $row;
    }

    /** @param array<string,mixed> $values */
    public function advance(ConnectionInterface $db, DataScope $scope, stdClass $claim, string $state, string $phase, array $values = []): void
    {
        SchemaRows::one($this->claim($db, $scope, $claim)->update(['state' => $state, 'phase' => $phase, 'claim_token' => null, 'lease_until' => null,
            'next_attempt_at' => $this->now(), ...$values]));
    }

    public function status(ConnectionInterface $db, stdClass $row): SchemaOperationStatus
    {
        $progress = $db->table('sys_attribute_backfills')->where('operation_id', $row->id);
        $failures = [];
        foreach ((clone $progress)->where('kind', 'error')->orderBy('record_id')->get(['record_id', 'seen_revision', 'error_code']) as $error) {
            $failures[] = ['record_id' => SchemaValue::text($error->record_id), 'seen_revision' => SchemaValue::text($error->seen_revision), 'error_code' => SchemaValue::text($error->error_code)];
        }

        $checkpoint = $row->cancel_requested === true || $row->phase === 'contract' ? 'cleanup' : ($row->phase === 'verify' || $row->phase === 'switch' ? 'verify' : 'scan');

        return new SchemaOperationStatus(new SchemaOperationRef(SchemaValue::text($row->id)), SchemaValue::text($row->state),
            new ClaimGeneration(SchemaValue::integer($row->generation)), SchemaValue::strings(json_decode(SchemaValue::text($row->blockers), true, flags: JSON_THROW_ON_ERROR)),
            $failures, SchemaValue::nullableText((clone $progress)->where('partition_id', $checkpoint)->value('cursor_record_id')));
    }

    public static function proofReference(stdClass $row): string
    {
        return SchemaValue::text($row->id).'/'.SchemaValue::text($row->plan_digest);
    }
}
