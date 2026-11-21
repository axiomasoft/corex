<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Exceptions\MediaOperationConflict;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use Vaulter\Database\VaulterDatabase;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;
use Vaulter\Media\Data\Snapshots\BlobSnapshot;
use Vaulter\Media\Enums\Cas\BlobStatus;
use Vaulter\Media\ValueObjects\Cas\BlobScope;

/** CoreX owns reference rows; Vaulter owns every byte and its liveness pins. */
#[Internal]
final readonly class MediaBlobs
{
    public function __construct(private VaulterDatabase $database, private DatabaseManager $connections, private BlobStore $store, private BlobPins $pins, private TenantContextResolver $tenants, private DataScopeFactory $scopes) {}

    public function accountId(): string
    {
        return $this->tenants->current()->account->id;
    }

    public function assertContext(ConnectionInterface $connection, MediaContext $context): void
    {
        $this->assertConnection($connection);
        $tenant = $this->tenants->current();

        if ($context->tenant->account->id !== $tenant->account->id
            || $context->tenant->workspace?->id !== $tenant->workspace?->id
            || $context->tenant->isBoxed() !== $tenant->isBoxed()) {
            throw new MediaOperationConflict('Media context does not match the active tenant scope.');
        }
        $scope = $this->scopes->forCurrentPrincipal($tenant);

        if ($scope->principalKind !== $context->actorKind || $scope->principalId !== $context->actorId) {
            throw new MediaOperationConflict('Media principal does not match the authenticated scope.');
        }
    }

    public function assertConnection(ConnectionInterface $connection): void
    {
        if ($connection !== $this->database->connection() || $connection !== $this->connections->connection()) {
            throw new MediaOperationConflict('CoreX media and Vaulter must share the active database connection.');
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(ConnectionInterface $connection, Closure $callback): mixed
    {
        $this->assertConnection($connection);

        return $this->database->transaction(static fn (): mixed => $callback());
    }

    public function commit(string $bytes, string $accountId): BlobSnapshot
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new MediaOperationConflict('Media stream could not be opened.');
        }

        try {
            fwrite($stream, $bytes);
            rewind($stream);

            $blob = $this->store->commit($stream, BlobScope::tenant($accountId));

            if ($blob->tenantId !== $accountId || $blob->status !== BlobStatus::Committed || ! $blob->intact
                || $blob->sizeBytes !== strlen($bytes) || ! hash_equals($blob->sha256, hash('sha256', $bytes))) {
                throw new MediaOperationConflict('Committed blob evidence does not match the owner request.');
            }

            return $blob;
        } finally {
            fclose($stream);
        }
    }

    /** @return resource */
    public function read(string $blobId): mixed
    {
        $stream = $this->store->read($blobId);

        if (! is_resource($stream)) {
            throw new MediaOperationConflict('Media stream is unavailable.');
        }

        return $stream;
    }

    public function pin(string $blobId, string $ownerType, string $referenceKey): void
    {
        $this->pins->pin($blobId, $ownerType, $referenceKey);
    }

    public function unpin(string $blobId, string $ownerType, string $referenceKey): void
    {
        $this->pins->unpin($blobId, $ownerType, $referenceKey);
    }
}
