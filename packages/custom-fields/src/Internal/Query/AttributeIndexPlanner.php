<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Query;

use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\SchemaOperationId;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Internal\Value;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use RuntimeException;

final readonly class AttributeIndexPlanner
{
    public function __construct(
        private ConnectionInterface $connection,
        private AttributeTables $tables,
        private SchemaOperations $operations,
        private readonly ClockInterface $clock,
    ) {}

    public function indexName(AttributeDefinition $definition): string
    {
        return 'sys_attr_ix_'.substr(hash('sha256', $definition->id->value."\n".$definition->revision), 0, 24);
    }

    public function expression(AttributeDefinition $definition): string
    {
        $cast = match ($definition->type) {
            'int' => 'bigint',
            'decimal' => 'numeric',
            default => throw new InvalidArgumentException('Expression index requires a measured int or decimal cast.'),
        };

        return '((values->>'.$this->literal($definition->key).')::'.$cast.')';
    }

    public function orderBy(AttributeDefinition $definition): string
    {
        return $this->expression($definition).', record_id';
    }

    public function activate(
        DataScope $scope,
        AttributeDefinition $definition,
        string $schemaHash,
        string $approvalDigest,
    ): AttributeIndexReceipt {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);

        if ($definition->index === IndexPolicy::None) {
            return new AttributeIndexReceipt(mode: 'none', ddl: false, indexName: null, expression: null, orderBy: null, owner: 'attributes');
        }

        if ($definition->index === IndexPolicy::Promoted) {
            return new AttributeIndexReceipt(mode: 'promoted', ddl: false, indexName: null, expression: null, orderBy: null, owner: 'subject');
        }

        $expression = $this->expression($definition);

        if ($this->dirtyRecords($definition) !== []) {
            throw new InvalidArgumentException('Dirty cast blocks expression index DDL.');
        }

        $name = $this->indexName($definition);
        $orderBy = $this->orderBy($definition);
        $before = $this->explain($definition->entity, $expression);
        $payload = [
            'field' => $definition->key,
            'attribute_id' => $definition->id->value,
            'revision' => $definition->revision,
            'index' => $name,
            'expression' => $expression,
        ];
        $queued = $this->operations->request(
            scope: $scope,
            change: new SchemaChange(
                entity: $definition->entity,
                kind: 'add',
                expectedSchemaHash: $schemaHash,
                definition: $payload,
                approvalDigest: $approvalDigest,
            ),
            key: new SchemaOperationId(key: 'index-'.$name, payloadHash: hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))),
        );

        $predicate = 'entity = '.$this->literal($definition->entity);

        if (! $this->indexExists($name)) {
            $this->connection->statement(
                "CREATE INDEX CONCURRENTLY {$name} ON {$this->tables->documents} ({$expression}, record_id) WHERE {$predicate}",
            );
        }

        if (! $this->indexExists($name)) {
            throw new RuntimeException('The measured attribute index was not created.');
        }

        $this->connection->table($this->tables->schemaOperations)->where('id', $queued->operation->id)->update([
            'status' => 'verified',
            'error_code' => null,
            'finished_at' => $this->clock->now(),
        ]);
        $after = $this->explain($definition->entity, $expression);

        if (! str_contains(json_encode($after, JSON_THROW_ON_ERROR), $name)) {
            throw new RuntimeException('The verified query did not use the expression B-tree.');
        }

        return new AttributeIndexReceipt(
            mode: 'expression_btree',
            ddl: true,
            indexName: $name,
            expression: $expression,
            orderBy: $orderBy,
            owner: 'attributes',
            explainBefore: $before,
            explainAfter: $after,
        );
    }

    /** @return list<string> */
    private function dirtyRecords(AttributeDefinition $definition): array
    {
        $cast = $definition->type === 'int' ? 'bigint' : 'numeric';

        return array_values($this->connection->table($this->tables->documents)
            ->where('entity', $definition->entity)
            ->whereRaw('values ?? ?', [$definition->key])
            ->whereRaw('not pg_input_is_valid(values->>?, ?)', [$definition->key, $cast])
            ->pluck('record_id')
            ->map(static fn (mixed $id): string => Value::text($id))
            ->all());
    }

    /** @return array<array-key, mixed> */
    private function explain(string $entity, string $expression): array
    {
        $sql = 'EXPLAIN (FORMAT JSON) SELECT record_id FROM '.$this->tables->documents
            .' WHERE entity = '.$this->literal($entity)." AND {$expression} IS NOT NULL ORDER BY {$expression}, record_id";
        $row = $this->connection->transaction(function () use ($sql): ?object {
            $this->connection->unprepared('SET LOCAL enable_seqscan = off');

            $row = $this->connection->selectOne($sql);

            if ($row !== null && ! is_object($row)) {
                throw new RuntimeException('Query plan did not return a row.');
            }

            return $row;
        });

        $plan = $row === null ? null : Value::field($row, 'QUERY PLAN');

        return Value::array(json_decode(is_string($plan) ? $plan : json_encode($plan, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR));
    }

    private function indexExists(string $index): bool
    {
        return $this->connection->table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $this->tables->documents)
            ->where('indexname', $index)
            ->exists();
    }

    private function literal(string $value): string
    {
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Attribute index literal is not an identifier.');
        }

        return "'{$value}'";
    }
}
