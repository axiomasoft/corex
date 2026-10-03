<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Data\ConversionClaim;
use CoreX\Media\Data\ConversionOperationRequest;
use CoreX\Media\Data\PurgeClaim;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

final class MediaOperationStore
{
    public function __construct(private readonly ConnectionInterface $connection, private readonly ClockInterface $clock) {}

    public function enqueuePurge(string $fileId): void
    {
        $this->connection->table('med_operations')->insertOrIgnore([
            'file_id' => $fileId,
            'operation_key' => "purge:{$fileId}",
            'kind' => 'purge',
            'state' => 'pending',
            'claim_epoch' => 0,
            'attempts' => 0,
            'next_attempt_at' => $this->clock->now(),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }

    public function claimPurge(string $fileId): ?PurgeClaim
    {
        return $this->connection->transaction(function () use ($fileId): ?PurgeClaim {
            $file = $this->connection->table('med_files')->where('id', $fileId)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'deleting') {
                return null;
            }

            $operation = $this->connection->table('med_operations')
                ->where('file_id', $fileId)
                ->where('kind', 'purge')
                ->lockForUpdate()
                ->first();

            if ($operation === null || ! in_array($operation->state, ['pending', 'unknown'], true)) {
                return null;
            }

            if ($this->notYetDue($operation->next_attempt_at)) {
                return null;
            }

            $epoch = ((int) $operation->claim_epoch) + 1;
            $this->connection->table('med_operations')->where('id', $operation->id)->update([
                'state' => 'claimed',
                'claim_epoch' => $epoch,
                'attempts' => ((int) $operation->attempts) + 1,
                'lease_until' => $this->clock->now()->add(new DateInterval('PT5M')),
                'updated_at' => $this->clock->now(),
            ]);

            $conversionKeys = $this->connection->table('med_conversions')->where('file_id', $fileId)->whereNotNull('object_key')->pluck('object_key')->all();
            $attemptKeys = $this->connection->table('med_operations')->where('file_id', $fileId)->whereNotNull('attempt_object_key')->pluck('attempt_object_key')->all();

            return new PurgeClaim((int) $operation->id, $file->id, $epoch, (int) $file->version, array_values(array_unique([$file->object_key, ...$conversionKeys, ...$attemptKeys])));
        });
    }

    public function completePurge(PurgeClaim $claim): void
    {
        $this->connection->transaction(function () use ($claim): void {
            $file = $this->connection->table('med_files')->where('id', $claim->fileId)->lockForUpdate()->first();
            $operation = $this->connection->table('med_operations')->where('id', $claim->operationId)->lockForUpdate()->first();

            if ($file === null || $operation === null || $file->state !== 'deleting' || (int) $file->version !== $claim->fileVersion || $operation->state !== 'claimed' || (int) $operation->claim_epoch !== $claim->epoch) {
                return;
            }

            $this->connection->table('med_files')->where('id', $claim->fileId)->update([
                'state' => 'deleted',
                'deleted_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->connection->table('med_operations')->where('id', $claim->operationId)->update([
                'state' => 'ready',
                'lease_until' => null,
                'updated_at' => $this->clock->now(),
            ]);
        });
    }

    public function markUnknown(PurgeClaim $claim): void
    {
        $this->connection->table('med_operations')
            ->where('id', $claim->operationId)
            ->where('state', 'claimed')
            ->where('claim_epoch', $claim->epoch)
            ->update([
                'state' => 'unknown',
                'lease_until' => null,
                'next_attempt_at' => $this->clock->now()->add(new DateInterval('PT1M')),
                'updated_at' => $this->clock->now(),
            ]);
    }

    public function enqueueConversion(string $fileId, string $profileKey, string $profileDigest, int $sourceVersion): void
    {
        $this->connection->table('med_operations')->insertOrIgnore([
            'file_id' => $fileId,
            'operation_key' => "conversion:{$fileId}:{$profileDigest}:{$sourceVersion}",
            'kind' => 'conversion',
            'state' => 'pending',
            'profile_key' => $profileKey,
            'profile_digest' => $profileDigest,
            'source_version' => $sourceVersion,
            'claim_epoch' => 0,
            'attempts' => 0,
            'next_attempt_at' => $this->clock->now(),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }

    public function claimConversion(string $fileId, string $profileKey): ?ConversionClaim
    {
        return $this->connection->transaction(function () use ($fileId, $profileKey): ?ConversionClaim {
            $file = $this->connection->table('med_files')->where('id', $fileId)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                return null;
            }

            $conversion = $this->connection->table('med_conversions')
                ->where('file_id', $fileId)
                ->where('profile_key', $profileKey)
                ->where('source_version', $file->version)
                ->where('state', 'pending')
                ->lockForUpdate()
                ->first();

            if ($conversion === null) {
                return null;
            }

            $operation = $this->connection->table('med_operations')
                ->where('file_id', $fileId)
                ->where('kind', 'conversion')
                ->where('profile_digest', $conversion->profile_digest)
                ->where('source_version', $file->version)
                ->lockForUpdate()
                ->first();

            if ($operation === null || ! in_array($operation->state, ['pending', 'unknown'], true) || ($this->notYetDue($operation->next_attempt_at))) {
                return null;
            }

            $epoch = ((int) $operation->claim_epoch) + 1;
            $attemptKey = "media/conversions/{$fileId}/{$conversion->profile_digest}/{$file->version}/{$epoch}";
            $this->connection->table('med_operations')->where('id', $operation->id)->update([
                'state' => 'claimed',
                'claim_epoch' => $epoch,
                'attempts' => ((int) $operation->attempts) + 1,
                'lease_until' => $this->clock->now()->add(new DateInterval('PT5M')),
                'attempt_object_key' => $attemptKey,
                'updated_at' => $this->clock->now(),
            ]);

            return new ConversionClaim((int) $operation->id, (int) $conversion->id, $file->id, $epoch, (int) $file->version, $file->object_key, $file->mime_type, $profileKey, $conversion->profile_digest, $attemptKey);
        });
    }

    public function completeConversion(ConversionClaim $claim, string $mimeType, int $sizeBytes, int $width, int $height): bool
    {
        return $this->connection->transaction(function () use ($claim, $mimeType, $sizeBytes, $width, $height): bool {
            $file = $this->connection->table('med_files')->where('id', $claim->fileId)->lockForUpdate()->first();
            $conversion = $this->connection->table('med_conversions')->where('id', $claim->conversionId)->lockForUpdate()->first();
            $operation = $this->connection->table('med_operations')->where('id', $claim->operationId)->lockForUpdate()->first();

            if ($file === null || $conversion === null || $operation === null || $file->state !== 'ready' || (int) $file->version !== $claim->fileVersion || $conversion->state !== 'pending' || $operation->state !== 'claimed' || (int) $operation->claim_epoch !== $claim->epoch) {
                return false;
            }

            $this->connection->table('med_conversions')->where('id', $claim->conversionId)->update([
                'state' => 'ready',
                'object_key' => $claim->attemptObjectKey,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
                'updated_at' => $this->clock->now(),
            ]);
            $this->connection->table('med_operations')->where('id', $claim->operationId)->update([
                'state' => 'ready',
                'lease_until' => null,
                'updated_at' => $this->clock->now(),
            ]);

            return true;
        });
    }

    public function markConversionUnknown(ConversionClaim $claim): void
    {
        $this->connection->table('med_operations')
            ->where('id', $claim->operationId)
            ->where('state', 'claimed')
            ->where('claim_epoch', $claim->epoch)
            ->update([
                'state' => 'unknown',
                'lease_until' => null,
                'next_attempt_at' => $this->clock->now()->add(new DateInterval('PT1M')),
                'updated_at' => $this->clock->now(),
            ]);
    }

    /** @return list<string> */
    public function duePurgeFileIds(int $limit = 100): array
    {
        $now = $this->clock->now();

        return $this->connection->table('med_operations')
            ->where('kind', 'purge')
            ->whereIn('state', ['pending', 'unknown'])
            ->where(static fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('file_id')
            ->all();
    }

    /** @return list<ConversionOperationRequest> */
    public function dueConversionRequests(int $limit = 100): array
    {
        $now = $this->clock->now();

        return $this->connection->table('med_operations')
            ->where('kind', 'conversion')
            ->whereIn('state', ['pending', 'unknown'])
            ->where(static fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $operation): ConversionOperationRequest => new ConversionOperationRequest($operation->file_id, (string) $operation->profile_key))
            ->all();
    }

    /** A stored attempt time is a database string; comparing it with a DateTimeImmutable directly never orders. */
    private function notYetDue(mixed $nextAttemptAt): bool
    {
        $now = $this->clock->now();

        if ($nextAttemptAt instanceof DateTimeInterface) {
            return $nextAttemptAt > $now;
        }

        // Timezone-less database wall time is read in the zone of the clock that wrote it.
        return is_string($nextAttemptAt) && new DateTimeImmutable($nextAttemptAt, $now->getTimezone()) > $now;
    }
}
