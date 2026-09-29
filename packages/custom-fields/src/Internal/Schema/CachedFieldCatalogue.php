<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Support\FieldSnapshot;
use CoreX\Data\DataScope;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class CachedFieldCatalogue implements FieldCatalogue
{
    public function __construct(
        private FieldCatalogue $source,
        private CacheRepository $cache,
        private string $sourceFingerprint,
        private string $policyVersion,
        private int $ttlSeconds = 300,
    ) {
        if ($this->sourceFingerprint === '' || $this->policyVersion === '' || $this->ttlSeconds < 1) {
            throw new InvalidArgumentException('Catalogue cache requires fingerprints and a positive TTL.');
        }
    }

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        $key = $this->key($scope, $entity);

        try {
            $cached = $this->cache->get($key);
        } catch (Throwable) {
            return $this->source->snapshot($scope, $entity);
        }

        if ($cached !== null) {
            if (! $cached instanceof FieldSnapshot || $cached->entity !== $entity) {
                try {
                    $this->cache->forget($key);
                } catch (Throwable) {
                }

                throw new RuntimeException('Cached field catalogue snapshot is malformed.');
            }

            return $cached;
        }

        $snapshot = $this->source->snapshot($scope, $entity);

        try {
            $this->cache->put($key, $snapshot, $this->ttlSeconds);
        } catch (Throwable) {
        }

        return $snapshot;
    }

    public function invalidate(DataScope $scope, string $entity): void
    {
        try {
            $this->cache->forget($this->key($scope, $entity));
        } catch (Throwable) {
        }
    }

    private function key(DataScope $scope, string $entity): string
    {
        return 'corex:attributes:catalogue:'.hash('sha256', json_encode([
            'account' => $scope->accountId,
            'workspace' => $scope->workspaceId,
            'boxed' => $scope->boxed,
            'connection' => $scope->connectionIdentity,
            'entity' => $entity,
            'source' => $this->sourceFingerprint,
            'policy' => $this->policyVersion,
        ], JSON_THROW_ON_ERROR));
    }
}
