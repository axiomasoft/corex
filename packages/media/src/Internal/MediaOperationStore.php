<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Media\Conversions\Data\ConversionStatus;
use CoreX\Media\Conversions\Enums\ConversionState;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Exceptions\MediaAccessDenied;
use CoreX\Media\Internal\Data\ConversionClaim;
use CoreX\Media\Internal\Data\ConversionOperationRequest;
use CoreX\Media\Internal\Data\PurgeClaim;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use Throwable;

#[Internal]
final readonly class MediaOperationStore
{
    private const string ConversionOwner = 'corex.media.conversion';

    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private MediaBlobs $blobs,
        private TenantContextResolver $contexts,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $current,
        private DataScopeCodec $codec,
        private StoredWorkIssuer $issuer,
        private ReferenceAdmission $admission,
        private StoredWorkRunner $runner,
    ) {}

    public function enqueuePurge(string $fileId): void
    {
        $this->blobs->assertConnection($this->connection);
        $scope = $this->enlist();
        $key = 'purge:'.$fileId;
        $proof = $this->issuer->issue($scope, 'corex.media.purge', $key);
        $this->connection->table('med_operations')->insertOrIgnore([
            ...$this->conversionIdentity($scope), ...$this->conversionFence($scope),
            'conversion_scope' => json_encode($this->codec->encode($scope), JSON_THROW_ON_ERROR),
            'scope_seal' => $proof->seal,
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

    public function purge(string $fileId): void
    {
        $scope = $this->currentConversionScope();
        $query = $this->connection->table('med_operations')->where('operation_key', 'purge:'.$fileId)
            ->where('kind', 'purge')->where($this->conversionFence($scope))
            ->where('account_id', $scope->accountId)->where('account_incarnation', $scope->accountIncarnation);

        if ($scope->workspaceId !== null) {
            $query->where($this->conversionIdentity($scope));
        }
        $row = $query->first();

        if ($row === null) {
            return;
        }
        $this->runner->runStored($this->conversionScope($row), new StoredWorkProof('corex.media.purge', 'purge:'.$fileId, Value::text($row->scope_seal)), function () use ($fileId): void {
            $claim = $this->claimPurge($fileId);

            if ($claim !== null) {
                try {
                    $this->completePurge($claim);
                } catch (Throwable $exception) {
                    $this->markUnknown($claim);

                    throw $exception;
                }
            }
        });
    }

    public function claimPurge(string $fileId): ?PurgeClaim
    {
        return $this->blobs->transaction($this->connection, function () use ($fileId): ?PurgeClaim {
            $scope = $this->enlist();
            $file = $this->connection->table('med_files')->where($this->conversionIdentity($scope))->where('id', $fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();

            if ($file === null || $file->state !== 'deleting') {
                return null;
            }

            $operation = $this->connection->table('med_operations')
                ->where('file_id', $fileId)
                ->where($this->conversionIdentity($scope))->where($this->conversionFence($scope))->where('kind', 'purge')
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

            return new PurgeClaim(operationId: Value::integer($operation->id), fileId: Value::text($file->id), epoch: $epoch, fileVersion: Value::integer($file->version), scope: $scope);
        });
    }

    public function completePurge(PurgeClaim $claim): void
    {
        $this->blobs->transaction($this->connection, function () use ($claim): void {
            $this->admission->enlist($claim->scope, $this->connection, new ModuleRef('axiomasoft/corex-media'));
            $file = $this->connection->table('med_files')->where($this->conversionIdentity($claim->scope))->where('id', $claim->fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();
            $operation = $this->connection->table('med_operations')->where('id', $claim->operationId)->lockForUpdate()->first();

            if ($file === null || $operation === null || ! $this->notYetDue($operation->lease_until) || $file->state !== 'deleting' || Value::integer($file->version) !== $claim->fileVersion || $operation->state !== 'claimed' || Value::integer($operation->claim_epoch) !== $claim->epoch) {
                return;
            }

            $conversions = $this->connection->table('med_conversions')->where('file_id', $claim->fileId)->lockForUpdate()->get();
            $released = [];
            foreach ($conversions as $conversion) {
                if ($conversion->blob_id !== null) {
                    $key = Value::text($conversion->id);
                    $this->blobs->unpin(Value::text($conversion->blob_id), 'corex.media.conversion', $key);
                    $released[] = [$conversion->blob_id, 'corex.media.conversion', $key];
                }
            }

            if ($file->blob_id !== null) {
                $this->blobs->unpin(Value::text($file->blob_id), 'corex.media.file', $claim->fileId);
                $released[] = [$file->blob_id, 'corex.media.file', $claim->fileId];
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
                'released_pins' => json_encode($released, JSON_THROW_ON_ERROR),
                'lease_until' => null,
                'updated_at' => $this->clock->now(),
            ]);
        });
    }

    public function markUnknown(PurgeClaim $claim): void
    {
        try {
            $this->current->assert($claim->scope, $this->connection);
        } catch (ScopeContextMismatch) {
            return;
        }
        $this->connection->table('med_operations')->where($this->conversionIdentity($claim->scope))->where($this->conversionFence($claim->scope))
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

    public function currentConversionScope(): DataScope
    {
        $scope = $this->scopes->forCurrentPrincipal($this->contexts->current());
        $this->current->assert($scope, $this->connection);

        return $scope;
    }

    /** @return array{account_id: string, account_incarnation: string, workspace_scope: string} */
    public function conversionIdentity(DataScope $scope): array
    {
        return [
            'account_id' => $scope->accountId,
            'account_incarnation' => $scope->accountIncarnation,
            'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId,
        ];
    }

    public function enlist(ModuleRef ...$targets): DataScope
    {
        $scope = $this->currentConversionScope();
        $this->admission->enlist($scope, $this->connection, new ModuleRef('axiomasoft/corex-media'), ...$targets);

        return $scope;
    }

    public function status(MediaConversionRef $ref): ConversionStatus
    {
        $scope = $this->currentConversionScope();
        $op = $this->conversionOperation($ref->fileId, $ref->profile, null, $ref->id, $scope)
            ->where($this->conversionIdentity($scope))->first();

        if ($op === null) {
            throw new MediaAccessDenied('Conversion reference unavailable.');
        }
        $state = match ($op->state) {
            'ready' => ConversionState::Ready, 'pending' => ConversionState::Pending,
            'claimed' => ConversionState::Processing, 'superseded', 'failed' => ConversionState::Failed,
            default => ConversionState::Unknown,
        };

        if ($state !== ConversionState::Ready) {
            return new ConversionStatus($state);
        }
        $output = $this->connection->table('med_conversions')->where($this->conversionIdentity($scope))
            ->where('file_id', $ref->fileId)->where('profile_key', $ref->profile)->where('profile_digest', $op->profile_digest)
            ->where('source_version', $op->source_version)->where('blob_id', $op->blob_receipt)->where('state', 'ready')->first();

        if ($output === null || ! is_string($op->blob_receipt)) {
            return new ConversionStatus(ConversionState::Unknown);
        }

        return new ConversionStatus($state, $op->blob_receipt, Value::text($output->sha256), Value::integer($output->width), Value::integer($output->height));
    }

    public function enqueueConversion(string $fileId, string $profileKey, string $profileDigest, int $sourceVersion): string
    {
        $this->blobs->assertConnection($this->connection);

        if ($this->connection->transactionLevel() < 1) {
            throw new LogicException('A conversion intent belongs to the active Media transaction.');
        }

        $scope = $this->currentConversionScope();
        $identity = $this->conversionIdentity($scope);
        $key = $this->conversionKey($scope, $fileId, $profileKey, $profileDigest, $sourceVersion);
        $proof = $this->issuer->issue($scope, self::ConversionOwner, $key);
        $now = $this->clock->now();

        $this->connection->table('med_operations')->insertOrIgnoreReturning([
            ...$identity,
            ...$this->conversionFence($scope),
            'file_id' => $fileId,
            'operation_key' => $key,
            'kind' => 'conversion',
            'state' => 'pending',
            'profile_key' => $profileKey,
            'profile_digest' => $profileDigest,
            'source_version' => $sourceVersion,
            'conversion_scope' => json_encode($this->codec->encode($scope), JSON_THROW_ON_ERROR),
            'scope_seal' => $proof->seal,
            'next_attempt_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['id'], ['operation_key']);
        $this->connection->table('med_operations')->where('operation_key', $key)->where('state', 'superseded')
            ->where($this->conversionFence($scope))
            ->increment('claim_epoch', 1, [
                'state' => 'pending', 'lease_until' => null, 'claim_token' => null,
                'next_attempt_at' => $now, 'updated_at' => $now,
            ]);

        return $key;
    }

    /** @return array{DataScope, StoredWorkProof}|null */
    public function conversionOrigin(string $fileId, string $profileKey, string $operationKey): ?array
    {
        $this->blobs->assertConnection($this->connection);
        $row = $this->conversionOperation($fileId, $profileKey, null, $operationKey, $this->currentConversionScope())
            ->first();

        if ($row === null) {
            return null;
        }

        $scope = $this->conversionScope($row);

        if (! hash_equals($this->conversionKey($scope, $fileId, $profileKey, Value::text($row->profile_digest), Value::integer($row->source_version)), $operationKey)) {
            return null;
        }

        return [
            $scope,
            new StoredWorkProof(self::ConversionOwner, Value::text($row->operation_key), Value::text($row->scope_seal)),
        ];
    }

    public function claimConversion(string $fileId, string $profileKey, string $profileDigest, ?string $operationKey = null): ?ConversionClaim
    {
        return $this->blobs->transaction($this->connection, function () use ($fileId, $profileKey, $profileDigest, $operationKey): ?ConversionClaim {
            $file = $this->connection->table('med_files')->where('id', $fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                return null;
            }

            $scope = $this->enlist();
            $identity = $this->conversionIdentity($scope);
            // Old deployments cannot publish under the digest of a different driver.
            $this->connection->table('med_operations')->where($identity)->where($this->conversionFence($scope))->where('file_id', $fileId)->where('profile_key', $profileKey)
                ->where('profile_digest', '!=', $profileDigest)->whereIn('state', ['pending', 'unknown', 'claimed'])
                ->update(['state' => 'superseded', 'lease_until' => null, 'updated_at' => $this->clock->now()]);
            $this->connection->table('med_conversions')->where($identity)->where('file_id', $fileId)->where('profile_key', $profileKey)
                ->where('profile_digest', '!=', $profileDigest)->where('state', 'pending')
                ->update(['state' => 'superseded', 'updated_at' => $this->clock->now()]);

            $conversion = $this->connection->table('med_conversions')->where($identity)
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

            $operation = $this->conversionOperation($fileId, $profileKey, $profileDigest, $operationKey, $scope)
                ->lockForUpdate()->first();

            if ($operation === null || ! $this->claimable($operation)) {
                return null;
            }

            $scope = $this->conversionScope($operation);
            $this->current->assert($scope, $this->connection);
            $token = (string) Str::uuid7();
            $epoch = (Value::integer($operation->claim_epoch)) + 1;
            $this->connection->table('med_operations')->where('id', $operation->id)->update([
                'state' => 'claimed',
                'claim_epoch' => $epoch,
                'claim_token' => $token,
                'attempts' => (Value::integer($operation->attempts)) + 1,
                'lease_until' => $this->clock->now()->add(new DateInterval('PT5M')),
                'updated_at' => $this->clock->now(),
            ]);

            return new ConversionClaim(operationId: Value::integer($operation->id), scope: $scope, token: $token, conversionId: Value::integer($conversion->id), fileId: Value::text($file->id), epoch: $epoch, fileVersion: Value::integer($file->version), sourceBlobId: Value::text($file->blob_id), accountId: Value::text($file->account_id), sourceMimeType: Value::text($file->mime_type), sourceHash: Value::text($file->sha256), sourceSize: Value::integer($file->size_bytes), profileKey: $profileKey, profileDigest: Value::text($conversion->profile_digest));
        });
    }

    public function completeConversion(ConversionClaim $claim, string $bytes, string $mimeType, int $sizeBytes, int $width, int $height): bool
    {
        return $this->blobs->transaction($this->connection, function () use ($claim, $bytes, $mimeType, $sizeBytes, $width, $height): bool {
            $file = $this->connection->table('med_files')->where('id', $claim->fileId)->where('account_id', $this->blobs->accountId())->lockForUpdate()->first();
            $conversion = $this->connection->table('med_conversions')->where('id', $claim->conversionId)->lockForUpdate()->first();
            $operation = $this->connection->table('med_operations')->where('id', $claim->operationId)->lockForUpdate()->first();

            if ($file === null || $conversion === null || $operation === null || $file->state !== 'ready' || Value::integer($file->version) !== $claim->fileVersion || $conversion->state !== 'pending' || $operation->state !== 'claimed' || Value::integer($operation->claim_epoch) !== $claim->epoch
                || $operation->claim_token !== $claim->token || ! $this->notYetDue($operation->lease_until)) {
                return false;
            }

            $this->admission->enlist($claim->scope, $this->connection, new ModuleRef('axiomasoft/corex-media'), ...ReferenceWrites::targets($claim->scope, 'axiomasoft/corex-media', 'conversion-profile', ['profile' => $claim->profileKey, 'digest' => $claim->profileDigest]));
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
            $changed = $this->liveConversionClaim($claim)->update([
                'state' => 'ready',
                'blob_receipt' => $blob->id,
                'lease_until' => null,
                'updated_at' => $this->clock->now(),
            ]);

            if ($changed !== 1) {
                throw new LogicException('Media conversion lost its claim before committing the Vaulter receipt.');
            }

            return true;
        });
    }

    public function markConversionUnknown(ConversionClaim $claim): void
    {
        $this->blobs->assertConnection($this->connection);
        $this->current->assert($claim->scope, $this->connection);
        $this->liveConversionClaim($claim)
            ->update([
                'state' => 'unknown',
                'lease_until' => null,
                'next_attempt_at' => $this->clock->now()->add(new DateInterval('PT1M')),
                'updated_at' => $this->clock->now(),
            ]);
    }

    private function liveConversionClaim(ConversionClaim $claim): Builder
    {
        return $this->connection->table('med_operations')->where('id', $claim->operationId)->where('file_id', $claim->fileId)
            ->where($this->conversionFence($claim->scope))->where($this->conversionIdentity($claim->scope))
            ->where('state', 'claimed')->where('claim_epoch', $claim->epoch)->where('claim_token', $claim->token)
            ->where('lease_until', '>', $this->clock->now());
    }

    private function conversionOperation(string $fileId, string $profileKey, ?string $profileDigest, ?string $key, DataScope $scope): Builder
    {
        $files = $this->connection->table('med_files')->where('id', $fileId)->where('account_id', $scope->accountId);
        $query = $this->connection->table('med_operations')->where('file_id', $fileId)->where('kind', 'conversion')
            ->where('profile_key', $profileKey)->where($this->conversionFence($scope))
            ->when($profileDigest !== null, fn (Builder $row) => $row->where('profile_digest', $profileDigest))
            ->where('source_version', (clone $files)->select('version'))->whereIn('file_id', $files->select('id'));

        if ($key !== null) {
            $query->where('operation_key', $key);
        }

        return $key === null || $scope->workspaceId !== null
            ? $query->where($this->conversionIdentity($scope))
            : $query->where('account_id', $scope->accountId)->where('account_incarnation', $scope->accountIncarnation);
    }

    /** @return array{deployment_id: string, binding_id: string, binding_generation: int, restore_epoch: string} */
    private function conversionFence(DataScope $scope): array
    {
        return [
            'deployment_id' => $scope->deploymentId, 'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration, 'restore_epoch' => $scope->restoreEpoch,
        ];
    }

    public function conversionKey(DataScope $scope, string $fileId, string $profileKey, string $profileDigest, int $sourceVersion): string
    {
        return 'conversion:'.hash('sha256', json_encode([
            $this->conversionIdentity($scope), $fileId, $profileKey, $profileDigest, $sourceVersion,
        ], JSON_THROW_ON_ERROR));
    }

    private function conversionScope(object $operation): DataScope
    {
        return $this->codec->decode(JsonDocuments::map(new JsonDocument(Value::text(Value::field($operation, 'conversion_scope')))));
    }

    /** @return list<string> */
    public function duePurgeFileIds(int $limit = 100): array
    {
        $scope = $this->currentConversionScope();
        $now = $this->clock->now();

        return array_values($this->connection->table('med_operations')
            ->where('kind', 'purge')->where($this->conversionFence($scope))
            ->where('account_id', $scope->accountId)->where('account_incarnation', $scope->accountIncarnation)
            ->when($scope->workspaceId !== null, fn (Builder $q) => $q->where($this->conversionIdentity($scope)))
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
        $scope = $this->currentConversionScope();

        return array_values($this->connection->table('med_operations')
            ->where('kind', 'conversion')->where($this->conversionFence($scope))
            ->where('account_id', $scope->accountId)->where('account_incarnation', $scope->accountIncarnation)
            ->when($scope->workspaceId !== null, fn (Builder $query) => $query->where($this->conversionIdentity($scope)))
            ->where(fn (Builder $query) => $this->due($query, $now))
            ->whereExists($this->connection->table('med_files')->select('id')->where('account_id', $scope->accountId)
                ->where('state', 'ready')->whereColumn('med_files.id', 'med_operations.file_id')
                ->whereColumn('med_files.version', 'med_operations.source_version'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $operation): ConversionOperationRequest => new ConversionOperationRequest(fileId: Value::text($operation->file_id), profileKey: Value::text($operation->profile_key), operationKey: Value::text($operation->operation_key)))
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
