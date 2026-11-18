<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Internal\Support\Validation;
use CoreX\CustomFields\Internal\Value;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;
use RuntimeException;
use stdClass;
use ValueError;

/** @phpstan-import-type JsonMap from ValueBag */
#[Internal]
final readonly class DatabaseFieldCatalogue implements FieldCatalogue
{
    /** @param array<string, bool> $drivers */
    public function __construct(
        private ConnectionInterface $connection,
        private AttributeTables $tables,
        private ScopeConnectionGuard $guard,
        private array $drivers = [],
    ) {}

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        $this->guard->assertMatches($scope);

        $latest = [];
        foreach ($this->connection->table($this->tables->definitions)
            ->where('entity', $entity)
            ->orderBy('attribute_id')
            ->orderBy('revision')
            ->get() as $row) {
            $definition = $this->hydrate($row);
            $latest[$definition->id->value] = $definition;
        }

        /** @var list<AttributeDefinition> $definitions */
        $definitions = array_values($latest);
        $definitions = AttributeDefinition::canonicalList($definitions);
        $hash = AttributeDefinition::schemaHash(entity: $entity, definitions: $definitions);

        return new FieldSnapshot(
            entity: $entity,
            schemaVersion: $hash,
            schemaHash: $hash,
            activeFields: Validation::validatedMapList(values: array_map(
                static fn (AttributeDefinition $definition): array => $definition->snapshotRow(),
                $definitions,
            ), name: 'active fields'),
            driverCapabilities: $this->drivers,
        );
    }

    /** @param array<string, string> $reservations key => current|alias */
    public function publish(DataScope $scope, AttributeDefinition $definition, array $reservations): void
    {
        $this->guard->assertMatches($scope);

        if ($definition->id->isLegacy()) {
            throw new InvalidArgumentException('Legacy attribute identities must be pinned before database publication.');
        }

        if (($reservations[$definition->key] ?? null) !== 'current') {
            throw new InvalidArgumentException('The current attribute key must have the current reservation role.');
        }

        foreach ($reservations as $key => $role) {
            if ($key === '' || ! in_array($role, ['current', 'alias'], true)) {
                throw new InvalidArgumentException('Attribute reservations require a key and current or alias role.');
            }
        }

        $canonical = $definition->canonical();
        $definitionHash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        $this->connection->transaction(function () use ($definition, $definitionHash, $reservations): void {
            $this->connection->table($this->tables->definitions)->insert([
                'attribute_id' => $definition->id->value,
                'revision' => $definition->revision,
                'entity' => $definition->entity,
                'key' => $definition->key,
                'type' => $definition->type,
                'lifecycle' => $definition->lifecycle->value,
                'required' => $definition->required,
                'searchable' => $definition->searchable,
                'constraints' => json_encode($definition->constraints, JSON_THROW_ON_ERROR),
                'read_keys' => json_encode($definition->readKeys, JSON_THROW_ON_ERROR),
                'write_keys' => json_encode($definition->writeKeys, JSON_THROW_ON_ERROR),
                'index_policy' => json_encode($definition->index->value, JSON_THROW_ON_ERROR),
                'meta' => json_encode($definition->meta, JSON_THROW_ON_ERROR),
                'definition_hash' => $definitionHash,
            ]);

            $this->connection->table($this->tables->keyReservations)
                ->where('entity', $definition->entity)
                ->where('attribute_id', $definition->id->value)
                ->delete();

            foreach ($reservations as $key => $role) {
                $this->connection->table($this->tables->keyReservations)->insert([
                    'entity' => $definition->entity,
                    'key' => $key,
                    'attribute_id' => $definition->id->value,
                    'current_revision' => $definition->revision,
                    'role' => $role,
                    'created_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
                    'updated_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
                ]);
            }
        });
    }

    private function hydrate(stdClass $row): AttributeDefinition
    {
        try {
            $index = $this->decode(value: $row->index_policy ?? null, name: 'index policy');

            if (is_array($index)) {
                $index = $index['kind'] ?? null;
            }

            $definition = new AttributeDefinition(
                id: new AttributeId(Value::text($row->attribute_id ?? '')),
                entity: Value::text($row->entity ?? ''),
                key: Value::text($row->key ?? ''),
                revision: Value::integer($row->revision ?? 0),
                type: Value::text($row->type ?? ''),
                lifecycle: AttributeLifecycle::from(Value::text($row->lifecycle ?? '')),
                required: (bool) ($row->required ?? false),
                searchable: (bool) ($row->searchable ?? false),
                constraints: $this->map(value: $row->constraints ?? null, name: 'constraints'),
                readKeys: $this->list(value: $row->read_keys ?? null, name: 'read keys'),
                writeKeys: $this->list(value: $row->write_keys ?? null, name: 'write keys'),
                index: IndexPolicy::from(is_string($index) ? $index : ''),
                meta: $this->map(value: $row->meta ?? null, name: 'meta'),
            );
        } catch (JsonException|InvalidArgumentException|ValueError $exception) {
            throw new RuntimeException('Stored attribute definition is malformed.', previous: $exception);
        }

        $expected = hash('sha256', json_encode($definition->canonical(), JSON_THROW_ON_ERROR));

        if (! hash_equals($expected, Value::text($row->definition_hash ?? ''))) {
            throw new RuntimeException('Stored attribute definition hash does not match its canonical content.');
        }

        return $definition;
    }

    /** @return JsonMap */
    private function map(mixed $value, string $name): array
    {
        $decoded = $this->decode(value: $value, name: $name);

        if (! is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new RuntimeException("Stored attribute {$name} must be a JSON object.");
        }

        $decoded = Validation::validatedMap(value: $decoded, name: $name);

        return $decoded;
    }

    /** @return list<string> */
    private function list(mixed $value, string $name): array
    {
        $decoded = $this->decode(value: $value, name: $name);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException("Stored attribute {$name} must be a JSON list.");
        }
        foreach ($decoded as $item) {
            if (! is_string($item)) {
                throw new RuntimeException("Stored attribute {$name} must contain strings.");
            }
        }

        return $decoded;
    }

    private function decode(mixed $value, string $name): mixed
    {
        if (! is_string($value)) {
            throw new RuntimeException("Stored attribute {$name} must be JSON.");
        }

        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }
}
