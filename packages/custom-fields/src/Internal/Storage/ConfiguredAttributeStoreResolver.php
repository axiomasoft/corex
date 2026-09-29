<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Storage;

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\AttributeStoreResolver;
use CoreX\CustomFields\Data\AttributeStoragePolicy;
use RuntimeException;

final readonly class ConfiguredAttributeStoreResolver implements AttributeStoreResolver
{
    public function __construct(
        private AttributeStoragePolicy $policy,
        private ?AttributeStore $jsonb,
        private ?AttributeStore $eav,
    ) {}

    public function forEntity(string $entity): AttributeStore
    {
        $driver = $this->policy->driverFor($entity);
        $store = match ($driver) {
            'jsonb' => $this->jsonb,
            'eav' => $this->eav,
            default => null,
        };

        if (! $store instanceof AttributeStore) {
            throw new RuntimeException("Attribute storage [{$driver}] is unavailable for [{$entity}].");
        }

        return $store;
    }
}
