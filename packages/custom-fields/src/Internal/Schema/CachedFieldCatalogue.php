<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\Data\Data\DataScope;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use RuntimeException;
use Throwable;

#[Internal]
final readonly class CachedFieldCatalogue implements FieldCatalogue
{
    public function __construct(
        private FieldCatalogue $source,
        private CurrentWorkScope $current,
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
        $this->current->assert($scope);
        $key = $this->key(scope: $scope, entity: $entity);

        try {
            $cached = $this->cache->get($key);
        } catch (Throwable) {
            return $this->source->snapshot(scope: $scope, entity: $entity);
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

        $snapshot = $this->source->snapshot(scope: $scope, entity: $entity);

        try {
            $this->cache->put($key, $snapshot, $this->ttlSeconds);
        } catch (Throwable) {
        }

        return $snapshot;
    }

    private function key(DataScope $scope, string $entity): string
    {
        return 'corex:attributes:catalogue:'.hash('sha256', json_encode([
            'scope' => $scope,
            'entity' => $entity,
            'source' => $this->sourceFingerprint,
            'policy' => $this->policyVersion,
        ], JSON_THROW_ON_ERROR));
    }
}
