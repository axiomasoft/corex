<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Internal\Support\Validation;
use CoreX\CustomFields\Internal\Values\AttributeValueTransformerRegistry;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DefinitionEvolution
{
    public function __construct(private AttributeValueTransformerRegistry $transformers) {}

    public function assertTransition(AttributeLifecycle $from, AttributeLifecycle $to): void
    {
        $allowed = match ($from) {
            AttributeLifecycle::Draft => [AttributeLifecycle::Active],
            AttributeLifecycle::Active => [AttributeLifecycle::Deprecated, AttributeLifecycle::Migrating],
            AttributeLifecycle::Deprecated => [AttributeLifecycle::Active, AttributeLifecycle::Migrating, AttributeLifecycle::Retired],
            AttributeLifecycle::Migrating => [AttributeLifecycle::Active, AttributeLifecycle::Deprecated],
            AttributeLifecycle::Retired => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Attribute lifecycle cannot move from {$from->value} to {$to->value}.");
        }
    }

    public function expand(
        AttributeDefinition $current,
        string $newKey,
        string $newType,
        ?string $transformer = null,
    ): AttributeDefinition {
        $this->assertTransition(from: $current->lifecycle, to: AttributeLifecycle::Migrating);

        if ($current->id->isLegacy()) {
            throw new InvalidArgumentException('Legacy attribute identities cannot be evolved before they are pinned.');
        }

        if ($newKey === $current->key && $newType === $current->type) {
            throw new InvalidArgumentException('Attribute evolution must change the key or type.');
        }

        if ($newType !== $current->type && ($transformer === null || ! $this->transformers->has($transformer) || ! $this->transformers->isReversible($transformer))) {
            throw new InvalidArgumentException('Attribute type evolution requires a registered reversible transformer.');
        }

        return $this->revision(
            current: $current,
            key: $newKey,
            type: $newType,
            lifecycle: AttributeLifecycle::Migrating,
            readKeys: [$newKey, $current->key],
            writeKeys: [$current->key, $newKey],
            meta: array_merge($current->meta, [
                'evolution_stage' => 'expanded',
                'evolution_source_key' => $current->key,
                'evolution_source_lifecycle' => $current->lifecycle->value,
                'evolution_source_type' => $current->type,
                'evolution_target_key' => $newKey,
                'evolution_target_type' => $newType,
                'evolution_transformer' => $transformer,
                'evolution_transformer_direction' => 'forward',
            ]),
        );
    }

    public function switch(
        AttributeDefinition $migrating,
        string $expectedDigest,
        string $sourceDigest,
        string $targetDigest,
        DateTimeImmutable $switchedAt,
    ): AttributeDefinition {
        if ($migrating->lifecycle !== AttributeLifecycle::Migrating || ($migrating->meta['evolution_stage'] ?? null) !== 'expanded') {
            throw new InvalidArgumentException('Only an expanded migrating definition can switch writes.');
        }

        if ($expectedDigest === '' || ! hash_equals($expectedDigest, $sourceDigest) || ! hash_equals($sourceDigest, $targetDigest)) {
            throw new InvalidArgumentException('Attribute backfill digest does not permit the write switch.');
        }

        $this->assertTransition(from: $migrating->lifecycle, to: AttributeLifecycle::Active);

        return $this->revision(
            current: $migrating,
            key: $migrating->key,
            type: $migrating->type,
            lifecycle: AttributeLifecycle::Active,
            readKeys: $migrating->readKeys,
            writeKeys: [$migrating->key],
            meta: array_merge($migrating->meta, [
                'evolution_stage' => 'switched',
                'evolution_verified_digest' => $sourceDigest,
                'evolution_grace_started_at' => $switchedAt->format(DATE_ATOM),
            ]),
        );
    }

    public function contract(
        AttributeDefinition $switched,
        DateTimeImmutable $now,
        DateInterval $grace,
        int $fallbackCount,
    ): AttributeDefinition {
        $startedAt = $switched->meta['evolution_grace_started_at'] ?? null;

        if ($switched->lifecycle !== AttributeLifecycle::Active || ($switched->meta['evolution_stage'] ?? null) !== 'switched' || ! is_string($startedAt)) {
            throw new InvalidArgumentException('Only a verified switched definition can contract.');
        }

        if ($fallbackCount !== 0) {
            throw new InvalidArgumentException('Attribute evolution cannot contract while fallback reads remain.');
        }

        $graceStartedAt = new DateTimeImmutable($startedAt);

        if ($graceStartedAt->add($grace) > $now) {
            throw new InvalidArgumentException('Attribute evolution grace period has not elapsed.');
        }

        return $this->revision(
            current: $switched,
            key: $switched->key,
            type: $switched->type,
            lifecycle: AttributeLifecycle::Active,
            readKeys: [$switched->key],
            writeKeys: [$switched->key],
            meta: array_merge($switched->meta, ['evolution_stage' => 'contracted']),
        );
    }

    public function rollbackBeforeSwitch(AttributeDefinition $migrating): AttributeDefinition
    {
        if ($migrating->lifecycle !== AttributeLifecycle::Migrating || ($migrating->meta['evolution_stage'] ?? null) !== 'expanded') {
            throw new InvalidArgumentException('Only an expanded migrating definition can roll back before switch.');
        }

        $sourceLifecycle = AttributeLifecycle::from($this->metaString(definition: $migrating, key: 'evolution_source_lifecycle'));

        return $this->rollback(current: $migrating, lifecycle: $sourceLifecycle, stage: 'rolled_back_before_switch');
    }

    public function rollbackAfterSwitch(AttributeDefinition $switched): AttributeDefinition
    {
        if ($switched->lifecycle !== AttributeLifecycle::Active || ($switched->meta['evolution_stage'] ?? null) !== 'switched') {
            throw new InvalidArgumentException('Only a switched definition can roll back after switch.');
        }

        $sourceKey = $this->metaString(definition: $switched, key: 'evolution_source_key');
        $sourceType = $this->metaString(definition: $switched, key: 'evolution_source_type');
        $targetKey = $this->metaString(definition: $switched, key: 'evolution_target_key');
        $targetType = $this->metaString(definition: $switched, key: 'evolution_target_type');

        return $this->revision(
            current: $switched,
            key: $sourceKey,
            type: $sourceType,
            lifecycle: AttributeLifecycle::Migrating,
            readKeys: [$sourceKey, $targetKey],
            writeKeys: [$targetKey, $sourceKey],
            meta: array_merge($switched->meta, [
                'evolution_stage' => 'rolled_back_after_switch',
                'evolution_source_key' => $targetKey,
                'evolution_source_type' => $targetType,
                'evolution_target_key' => $sourceKey,
                'evolution_target_type' => $sourceType,
                'evolution_transformer_direction' => 'reverse',
            ]),
        );
    }

    /** @return array<string, string> */
    public function reservations(AttributeDefinition $definition): array
    {
        $reservations = [$definition->key => 'current'];

        if ($definition->lifecycle !== AttributeLifecycle::Migrating && ($definition->meta['evolution_stage'] ?? null) !== 'switched') {
            return $reservations;
        }

        foreach ($definition->readKeys as $key) {
            if ($key !== $definition->key) {
                $reservations[$key] = 'alias';
            }
        }

        return $reservations;
    }

    private function rollback(AttributeDefinition $current, AttributeLifecycle $lifecycle, string $stage): AttributeDefinition
    {
        $sourceKey = $this->metaString(definition: $current, key: 'evolution_source_key');
        $sourceType = $this->metaString(definition: $current, key: 'evolution_source_type');

        return $this->revision(
            current: $current,
            key: $sourceKey,
            type: $sourceType,
            lifecycle: $lifecycle,
            readKeys: [$sourceKey],
            writeKeys: [$sourceKey],
            meta: array_merge($current->meta, ['evolution_stage' => $stage]),
        );
    }

    /**
     * @param  list<string>  $readKeys
     * @param  list<string>  $writeKeys
     * @param  array<string, mixed>  $meta
     */
    private function revision(
        AttributeDefinition $current,
        string $key,
        string $type,
        AttributeLifecycle $lifecycle,
        array $readKeys,
        array $writeKeys,
        array $meta,
    ): AttributeDefinition {
        $meta = Validation::validatedMap(value: $meta, name: 'attribute metadata');

        return new AttributeDefinition(
            id: $current->id,
            entity: $current->entity,
            key: $key,
            revision: $current->revision + 1,
            type: $type,
            lifecycle: $lifecycle,
            required: $current->required,
            searchable: $current->searchable,
            constraints: $current->constraints,
            readKeys: array_values(array_unique($readKeys)),
            writeKeys: array_values(array_unique($writeKeys)),
            index: $current->index,
            meta: $meta,
        );
    }

    private function metaString(AttributeDefinition $definition, string $key): string
    {
        $value = $definition->meta[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Attribute definition is missing [{$key}] evolution metadata.");
        }

        return $value;
    }
}
