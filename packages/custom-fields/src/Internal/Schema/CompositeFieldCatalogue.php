<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\Data\DataScope;
use InvalidArgumentException;
use RuntimeException;

final readonly class CompositeFieldCatalogue implements FieldCatalogue
{
    /** @param array<string, FieldCatalogue> $sources Ordered from lower to higher precedence. */
    public function __construct(
        private array $sources,
        private CatalogueConflictPolicy $policy,
    ) {
        if ($this->sources === []) {
            throw new InvalidArgumentException('Composite catalogue requires at least one source.');
        }
        foreach ($this->sources as $name => $source) {
            if ($name === '') {
                throw new InvalidArgumentException('Composite catalogue sources must have unique names.');
            }
        }
    }

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        /** @var array<string, array{source: string, field: array<string, mixed>}> $byId */
        $byId = [];
        /** @var array<string, string> $byKey */
        $byKey = [];
        $drivers = [];

        foreach ($this->sources as $sourceName => $source) {
            $snapshot = $source->snapshot($scope, $entity);
            $drivers = array_replace($drivers, $snapshot->driverCapabilities);
            foreach ($snapshot->activeFields as $field) {
                if (! is_array($field)) {
                    throw new RuntimeException('Catalogue source returned a malformed field.');
                }

                $field = $this->normalize($field);
                $id = (string) ($field['id'] ?? 'legacy:'.$sourceName.':'.$field['key']);
                $key = (string) $field['key'];
                $existingId = $byKey[$key] ?? null;

                if ($existingId !== null && $existingId !== $id) {
                    $this->conflict('same_key_different_id', $sourceName, $key);
                }

                if (! isset($byId[$id])) {
                    $byId[$id] = ['source' => $sourceName, 'field' => $field];
                    $byKey[$key] = $id;

                    continue;
                }

                $existing = $byId[$id];
                $current = $existing['field'];

                if ($this->canonical($current) === $this->canonical($field)) {
                    continue;
                }

                $shadow = $this->shadow(
                    existing: $existing,
                    incomingSource: $sourceName,
                    incoming: $field,
                    difference: 'retirement_shadow',
                );

                if ($shadow !== null) {
                    unset($byKey[(string) $current['key']]);
                    $byId[$id] = $shadow;
                    $byKey[$key] = $id;

                    continue;
                }

                if ($current['key'] !== $field['key']) {
                    $newer = (int) $field['revision'] > (int) $current['revision'] ? $field : $current;
                    $older = $newer === $field ? $current : $field;
                    $aliases = [...$newer['read_keys'], ...$newer['write_keys']];

                    if ($newer['lifecycle'] !== 'migrating' || ! in_array($older['key'], $aliases, true)) {
                        $this->conflict('same_id_different_key_without_migrating_alias', $sourceName, $key);
                    }
                }

                if ((int) $current['revision'] === (int) $field['revision']) {
                    if ($current['type'] !== $field['type']) {
                        $this->conflict('same_id_revision_different_type', $sourceName, $key);
                    }

                    $this->conflict('same_id_revision_flags_constraints_index_differ', $sourceName, $key);
                }

                $winner = (int) $field['revision'] > (int) $current['revision']
                    ? ['source' => $sourceName, 'field' => $field]
                    : $existing;
                unset($byKey[(string) $current['key']], $byKey[(string) $field['key']]);
                $byId[$id] = $winner;
                $byKey[(string) $winner['field']['key']] = $id;
            }
        }

        $fields = array_map(static fn (array $entry): array => $entry['field'], array_values($byId));
        usort($fields, static fn (array $left, array $right): int => [$left['key'], $left['id'] ?? ''] <=> [$right['key'], $right['id'] ?? '']);
        $hash = hash('sha256', $entity."\n".$this->canonical($fields));

        return new FieldSnapshot(
            entity: $entity,
            schemaVersion: $hash,
            schemaHash: $hash,
            activeFields: $fields,
            driverCapabilities: $drivers,
        );
    }

    /**
     * @param  array{source: string, field: array<string, mixed>}  $existing
     * @param  array<string, mixed>  $incoming
     * @return array{source: string, field: array<string, mixed>}|null
     */
    private function shadow(array $existing, string $incomingSource, array $incoming, string $difference): ?array
    {
        $lifecycles = [
            (string) ($existing['field']['lifecycle'] ?? ''),
            (string) ($incoming['lifecycle'] ?? ''),
        ];

        if (count(array_unique($lifecycles)) !== 2
            || array_intersect($lifecycles, ['deprecated', 'retired']) === []) {
            return null;
        }

        $existingComparable = $existing['field'];
        $incomingComparable = $incoming;
        unset($existingComparable['lifecycle'], $incomingComparable['lifecycle']);

        if ($this->canonical($existingComparable) !== $this->canonical($incomingComparable)) {
            return null;
        }
        $attributeId = (string) ($incoming['id'] ?? '');

        if (! $this->policy->allowsShadow($incomingSource, $existing['source'], $attributeId, $difference)) {
            return null;
        }

        return ['source' => $incomingSource, 'field' => $incoming];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function normalize(array $field): array
    {
        $key = $field['key'] ?? $field['code'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Catalogue field key is required.');
        }

        $field['code'] = $key;
        $field['key'] = $key;
        $field['revision'] = (int) ($field['revision'] ?? 1);
        $field['lifecycle'] = (string) ($field['lifecycle'] ?? 'active');
        $field['read_keys'] = is_array($field['read_keys'] ?? null) ? array_values($field['read_keys']) : [];
        $field['write_keys'] = is_array($field['write_keys'] ?? null) ? array_values($field['write_keys']) : [];

        return $field;
    }

    private function conflict(string $reason, string $source, string $key): never
    {
        throw new RuntimeException("Catalogue conflict [{$reason}] from [{$source}] for [{$key}].");
    }

    /** @param array<array-key, mixed> $value */
    private function canonical(array $value): string
    {
        $sort = function (array $items) use (&$sort): array {
            if (array_is_list($items)) {
                return array_map(static fn (mixed $item): mixed => is_array($item) ? $sort($item) : $item, $items);
            }
            ksort($items);
            foreach ($items as $key => $item) {
                if (is_array($item)) {
                    $items[$key] = $sort($item);
                }
            }

            return $items;
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR);
    }
}
