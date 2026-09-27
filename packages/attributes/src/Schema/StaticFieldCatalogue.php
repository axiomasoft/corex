<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Schema;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Support\Data\DataScope;
use RuntimeException;

final readonly class StaticFieldCatalogue implements FieldCatalogue
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $definitions
     * @param  array<string, bool>  $drivers
     */
    public function __construct(
        private array $definitions,
        private array $drivers,
        private ?FieldCatalogue $appended = null,
    ) {}

    public function appending(FieldCatalogue $catalogue): self
    {
        return new self(
            definitions: $this->definitions,
            drivers: $this->drivers,
            appended: $catalogue,
        );
    }

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        $fields = $this->definitions[$entity] ?? [];
        usort($fields, static fn (array $left, array $right): int => (string) $left['code'] <=> (string) $right['code']);

        if ($this->appended instanceof FieldCatalogue) {
            try {
                $extra = $this->appended->snapshot($scope, $entity)->activeFields;
            } catch (RuntimeException) {
                $extra = [];
            }

            $fields = [...$fields, ...$extra];
        }

        $payload = json_encode($fields, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $entity."\n".$payload);

        return new FieldSnapshot(
            entity: $entity,
            schemaVersion: $hash,
            schemaHash: $hash,
            activeFields: $fields,
            driverCapabilities: $this->drivers,
        );
    }
}
