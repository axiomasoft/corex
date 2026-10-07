<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Internal;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Currency\Contracts\RateImporter;
use CoreX\Intl\Currency\Data\RateBatch;
use CoreX\Intl\Currency\Data\RateImportReceipt;
use CoreX\Intl\Currency\Data\RateSnapshot;
use CoreX\Intl\Currency\Exceptions\InvalidRateException;
use CoreX\Intl\Territory\Exceptions\AccessDeniedException;
use CoreX\Intl\Territory\Exceptions\OperationConflictException;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Override;
use Psr\Clock\ClockInterface;

final readonly class SqlRateImporter implements RateImporter
{
    public function __construct(
        private Connection $connection,
        private DataScopeAuthorizer $authorizer,
        private ClockInterface $clock,
        private int $batchLimit = 1000,
    ) {}

    #[Override]
    public function import(DataScope $scope, RateBatch $batch, WriteToken $token): RateImportReceipt
    {
        if (! $this->authorizer->authorize(scope: $scope, entity: 'rate-import', subjectId: $token->operationKey, fields: [], action: 'import')->allowed) {
            throw new AccessDeniedException('Rate import is not authorized for the current actor.');
        }

        if (count($batch->snapshots) > $this->batchLimit) {
            throw new InvalidRateException('Rate batch exceeds the configured import limit.');
        }

        foreach ($batch->snapshots as $snapshot) {
            $this->validateSnapshot(snapshot: $snapshot);
        }

        $existing = $this->connection->table('corex_rate_batches')
            ->where('operation_key', $token->operationKey)
            ->first();

        if ($existing !== null) {
            if (! hash_equals(Value::text($existing->payload_hash), $token->payloadHash)) {
                throw new OperationConflictException('Rate import operation key was reused with a different payload.');
            }

            return new RateImportReceipt(
                status: 'noop',
                generation: Value::integer($existing->generation),
                imported: 0,
                noop: count($batch->snapshots),
                conflicts: 0,
            );
        }

        $imported = 0;
        $noop = 0;
        $conflicts = 0;

        return $this->connection->transaction(function () use ($batch, $token, &$imported, &$noop, &$conflicts): RateImportReceipt {
            $generation = $batch->expectedGeneration;

            foreach ($batch->snapshots as $snapshot) {
                $payloadHash = RateSnapshotMapper::payloadHash(snapshot: $snapshot);
                $existingRow = $this->connection->table('corex_rate_snapshots')
                    ->where('source', $snapshot->source)
                    ->where('publication_id', $snapshot->publicationId)
                    ->where('publication_revision', $snapshot->publicationRevision)
                    ->where('base', $snapshot->base)
                    ->where('quote', $snapshot->quote)
                    ->where('valid_on', $snapshot->validOn)
                    ->first();

                if ($existingRow !== null) {
                    if (! hash_equals(Value::text($existingRow->payload_hash), $payloadHash)) {
                        $conflicts++;

                        continue;
                    }

                    $noop++;

                    continue;
                }

                $id = $snapshot->id !== '' ? $snapshot->id : (string) Str::ulid();

                $this->connection->table('corex_rate_snapshots')->insert([
                    'id' => $id,
                    'schema_version' => $snapshot->schema,
                    'base' => strtoupper($snapshot->base),
                    'quote' => strtoupper($snapshot->quote),
                    'rate_decimal' => $snapshot->rateDecimal,
                    'valid_on' => $snapshot->validOn,
                    'observed_at' => $snapshot->observedAt->format('Y-m-d H:i:s'),
                    'source' => $snapshot->source,
                    'publication_id' => $snapshot->publicationId,
                    'publication_revision' => $snapshot->publicationRevision,
                    'payload_hash' => $payloadHash,
                ]);

                $this->connection->table('corex_rate_current')->upsert([
                    [
                        'base' => strtoupper($snapshot->base),
                        'quote' => strtoupper($snapshot->quote),
                        'source' => $snapshot->source,
                        'snapshot_id' => $id,
                        'generation' => $generation,
                    ],
                ], ['base', 'quote', 'source'], ['snapshot_id', 'generation']);

                $imported++;
            }

            $this->connection->table('corex_rate_batches')->insert([
                'id' => (string) Str::ulid(),
                'operation_key' => $token->operationKey,
                'payload_hash' => $token->payloadHash,
                'format_hash' => $batch->formatHash,
                'source_manifest' => json_encode(JsonDocuments::map($batch->sourceManifest), JSON_THROW_ON_ERROR),
                'generation' => $generation,
                'state' => $conflicts > 0 ? 'conflict' : 'published',
                'created_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ]);

            return new RateImportReceipt(
                status: $conflicts > 0 ? 'conflict' : 'published',
                generation: $generation,
                imported: $imported,
                noop: $noop,
                conflicts: $conflicts,
            );
        });
    }

    private function validateSnapshot(RateSnapshot $snapshot): void
    {
        if ($snapshot->schema !== 'rate-snapshot/1') {
            throw new InvalidRateException('Rate snapshot schema is not supported.');
        }

        if (preg_match('/\A[0-9]+(?:\.[0-9]{1,18})?\z/', $snapshot->rateDecimal) !== 1) {
            throw new InvalidRateException('Rate decimal must be a positive canonical decimal string.');
        }

        if ((float) $snapshot->rateDecimal <= 0) {
            throw new InvalidRateException('Rate decimal must be positive.');
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $snapshot->validOn) !== 1) {
            throw new InvalidRateException('Rate valid_on must be a DATE string.');
        }
    }
}
