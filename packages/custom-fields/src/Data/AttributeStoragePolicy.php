<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use InvalidArgumentException;

final readonly class AttributeStoragePolicy
{
    /** @param array<string, string> $entities */
    public function __construct(public string $default, public array $entities)
    {
        if (! in_array($default, ['jsonb', 'eav'], true)) {
            throw new InvalidArgumentException('Attribute storage default must be jsonb or eav.');
        }

        foreach ($entities as $entity => $driver) {
            if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $entity) !== 1) {
                throw new InvalidArgumentException('Attribute storage entity name is invalid.');
            }

            if (! in_array($driver, ['jsonb', 'eav'], true)) {
                throw new InvalidArgumentException('Attribute storage is per entity and must be jsonb or eav.');
            }
        }
    }

    public function driverFor(string $entity): string
    {
        return $this->entities[$entity] ?? $this->default;
    }
}
