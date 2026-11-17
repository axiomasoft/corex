<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Internal\Data\ConversionClaim;
use CoreX\Media\Internal\Data\ConversionOperationRequest;
use CoreX\Media\Internal\Data\PurgeClaim;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Psr\Clock\ClockInterface;

final readonly class MediaOperationStore
{
    public function __construct(private ConnectionInterface $connection, private ClockInterface $clock, private MediaBlobs $blobs) {}

    public function enqueuePurge(string $fileId): void
    {
        $this->blobs->assertConnection($this->connection);
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
        return $this->blobs->transaction($this->connection, function () use ($fileId): ?PurgeClaim {
            $file = $this->connection->table('med_files')->where('id', $fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();

            if ($file === null || $file->state !== 'deleting') {
                return null;
            }

            $operation = $this->connection->table('med_operations')
                ->where('file_id', $fileId)
                ->where('kind', 'purge')
                ->lockForUpdate()
                ->first();

            if ($operation === null || ! $this->claimable($operation)) {
                return null;
            }

            $epoch = (Value::integer($operation->claim_epoch)) + 1;
            $this->connection->table('med_operations')->where('id', $operation->id)->update([
                'state' => 'claimed',
                'claim_epoch' => $epoch,
                'attempts' => (Value::integer($operation->attempts)) + 1,
                'lease_until' => $this->clock->now()->add(new DateInterval('PT5M')),
                'updated_at' => $this->clock->now(),
            ]);

            return new PurgeClaim(operationId: Value::integer($operation->id), fileId: Value::text($file->id), epoch: $epoch, fileVersion: Value::integer($file->version));
        });
    }

    public function completePurge(PurgeClaim $claim): void
    {
        $this->blobs->transaction($this->connection, function () use ($claim): void {
            $file = $this->connection->table('med_files')->where('id', $claim->fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();
            $operation = $this->connection->table('med_operations')->where('id', $claim->operationId)->lockForUpdate()->first();

            if ($file === null || $operation === null || $file->state !== 'deleting' || Value::integer($file->version) !== $claim->fileVersion || $operation->state !== 'claimed' || Value::integer($operation->claim_epoch) !== $claim->epoch) {
                return;
            }

            $conversions = $this->connection->table('med_conversions')->where('file_id', $claim->fileId)->lockForUpdate()->get();
            foreach ($conversions as $conversion) {
                if ($conversion->blob_id !== null) {
                    $this->blobs->unpin(Value::text($conversion->blob_id), 'corex.media.conversion', Value::text($conversion->id));
                }
            }

            if ($file->blob_id !== null) {
                $this->blobs->unpin(Value::text($file->blob_id), 'corex.media.file', $claim->fileId);
            }
            $this->connection->table('med_conversions')->where('file_id', $claim->fileId)->update(['blob_id' => null, 'state' => 'deleted']);
            $this->connection->table('med_files')->where('id', $claim->fileId)->update([
                'blob_id' => null,
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
        $this->blobs->assertConnection($this->connection);
        $this->connection->table('med_operations')
            ->where('id', $claim->operationId)
            ->where('file_id', $claim->fileId)
            ->whereIn('file_id', $this->connection->table('med_files')->select('id')->where('account_id', $this->blobs->accountId()))
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
        $this->blobs->assertConnection($this->connection);
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
        $this->connection->table('med_operations')
            ->where('operation_key', "conversion:{$fileId}:{$profileDigest}:{$sourceVersion}")
            ->where('state', 'superseded')
            ->increment('claim_epoch', 1, [
                'state' => 'pending', 'lease_until' => null,
                'next_attempt_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
    }

    public function claimConversion(string $fileId, string $profileKey, string $profileDigest): ?ConversionClaim
    {
        return $this->blobs->transaction($this->connection, function () use ($fileId, $profileKey, $profileDigest): ?ConversionClaim {
            $file = $this->connection->table('med_files')->where('id', $fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                return null;
            }

            // Old deployments cannot publish under the digest of a different driver.
            $this->connection->table('med_operations')->where('file_id', $fileId)->where('profile_key', $profileKey)
                ->where('profile_digest', '!=', $profileDigest)->whereIn('state', ['pending', 'unknown', 'claimed'])
                ->update(['state' => 'superseded', 'lease_until' => null, 'updated_at' => $this->clock->now()]);
            $this->connection->table('med_conversions')->where('file_id', $fileId)->where('profile_key', $profileKey)
                ->where('profile_digest', '!=', $profileDigest)->where('state', 'pending')
                ->update(['state' => 'superseded', 'updated_at' => $this->clock->now()]);

            $conversion = $this->connection->table('med_conversions')
                ->where('file_id', $fileId)
                ->where('profile_key', $profileKey)
                ->where('profile_digest', $profileDigest)
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

            if ($operation === null || ! $this->claimable($operation)) {
                return null;
            }

            $epoch = (Value::integer($operation->claim_epoch)) + 1;
            $this->connection->table('med_operations')->where('id', $operation->id)->update([
                'state' => 'claimed',
                'claim_epoch' => $epoch,
                'attempts' => (Value::integer($operation->attempts)) + 1,
                'lease_until' => $this->clock->now()->add(new DateInterval('PT5M')),
                'updated_at' => $this->clock->now(),
            ]);

            return new ConversionClaim(operationId: Value::integer($operation->id), conversionId: Value::integer($conversion->id), fileId: Value::text($file->id), epoch: $epoch, fileVersion: Value::integer($file->version), sourceBlobId: Value::text($file->blob_id), accountId: Value::text($file->account_id), sourceMimeType: Value::text($file->mime_type), profileKey: $profileKey, profileDigest: Value::text($conversion->profile_digest));
        });
    }

    public function completeConversion(ConversionClaim $claim, string $bytes, string $mimeType, int $sizeBytes, int $width, int $height): bool
    {
        return $this->blobs->transaction($this->connection, function () use ($claim, $bytes, $mimeType, $sizeBytes, $width, $height): bool {
            $file = $this->connection->table('med_files')->where('id', $claim->fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();
            $conversion = $this->connection->table('med_conversions')->where('id', $claim->conversionId)->lockForUpdate()->first();
            $operation = $this->connection->table('med_operations')->where('id', $claim->operationId)->lockForUpdate()->first();

            if ($file === null || $conversion === null || $operation === null || $file->state !== 'ready' || Value::integer($file->version) !== $claim->fileVersion || $conversion->state !== 'pending' || $operation->state !== 'claimed' || Value::integer($operation->claim_epoch) !== $claim->epoch) {
                return false;
            }

            $blob = $this->blobs->commit($bytes, $claim->accountId);
            $this->blobs->pin($blob->id, 'corex.media.conversion', (string) $claim->conversionId);
            $this->connection->table('med_conversions')->where('id', $claim->conversionId)->update([
                'state' => 'ready',
                'blob_id' => $blob->id,
                'sha256' => $blob->sha256,
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
        $this->blobs->assertConnection($this->connection);
        $this->connection->table('med_operations')
            ->where('id', $claim->operationId)
            ->where('file_id', $claim->fileId)
            ->whereIn('file_id', $this->connection->table('med_files')->select('id')->where('account_id', $this->blobs->accountId()))
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
        $this->blobs->assertConnection($this->connection);
        $now = $this->clock->now();

        return array_values($this->connection->table('med_operations')
            ->where('kind', 'purge')
            ->where(fn (Builder $query) => $this->due($query, $now))
            ->whereIn('file_id', $this->connection->table('med_files')->select('id')->where('account_id', $this->blobs->accountId()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('file_id')->map(Value::text(...))
            ->all());
    }

    /** @return list<ConversionOperationRequest> */
    public function dueConversionRequests(int $limit = 100): array
    {
        $this->blobs->assertConnection($this->connection);
        $now = $this->clock->now();

        return array_values($this->connection->table('med_operations')
            ->where('kind', 'conversion')
            ->where(fn (Builder $query) => $this->due($query, $now))
            ->whereIn('file_id', $this->connection->table('med_files')->select('id')->where('account_id', $this->blobs->accountId()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $operation): ConversionOperationRequest => new ConversionOperationRequest(fileId: Value::text($operation->file_id), profileKey: Value::text($operation->profile_key)))
            ->all());
    }

    private function claimable(object $operation): bool
    {
        if (Value::field($operation, 'state') === 'claimed') {
            return Value::field($operation, 'lease_until') !== null && ! $this->notYetDue(Value::field($operation, 'lease_until'));
        }

        return in_array(Value::field($operation, 'state'), ['pending', 'unknown'], true) && ! $this->notYetDue(Value::field($operation, 'next_attempt_at'));
    }

    private function due(Builder $query, DateTimeImmutable $now): void
    {
        $query->where(static fn (Builder $pending) => $pending->whereIn('state', ['pending', 'unknown'])
            ->where(static fn (Builder $at) => $at->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now)))
            ->orWhere(static fn (Builder $claimed) => $claimed->where('state', 'claimed')->where('lease_until', '<=', $now));
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
