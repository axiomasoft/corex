<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Query\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Query\Data\FilterPlan;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\SchemaHeads;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use RuntimeException;
use stdClass;

#[Internal]
final readonly class PostgresFieldMatches
{
    public function __construct(private FieldFilter $filters, private FieldCatalogue $catalogue, private SchemaHeads $heads, private DatabaseManager $databases, private WorkScopeRunner $runner, private CurrentWorkScope $current, private DataScopeAuthorizer $access) {}

    /** @return list<RecordRef> */
    public function matching(DataScope $scope, EntityHandle $entity, FilterNode $filter, RecordSelection $records): array
    {
        return $this->runner->run($scope, function () use ($scope, $entity, $filter, $records): array {
            $db = $this->databases->connection();
            $this->current->assert($scope, $db);

            return $db->transaction(function () use ($scope, $entity, $filter, $records, $db): array {
                $mapping = $this->heads->lockForWrite($db, $scope, $entity);
                $schema = $this->catalogue->snapshot($scope, $entity);
                $plan = $this->filters->compile($scope, $schema, $filter);
                $id = $filter->field->value;
                $slot = $mapping->slots[$id];
                $field = $mapping->schema->fields[$id];
                $selection = new FieldSelection([$id]);
                $allowed = fn (RecordRef $r): bool => $r->entity === $entity->value
                    && ($plan->recordIds === null || in_array($r->id, $plan->recordIds, true))
                    && $this->access->authorize($scope, $r, new FieldSelection([]), FieldUse::Read)->allowed
                    && $this->access->authorize($scope, $r, $selection, FieldUse::Filter)->allowed;
                $refs = array_filter($records->records, $allowed);

                if ($refs === []) {
                    return [];
                }
                [$sql, $parameters] = $this->predicate($plan, $slot, $field->type->value);
                // VALUES supplies requested IDs so an absent document also matches is-absent.
                $rows = $db->select('SELECT wanted.id FROM jsonb_array_elements_text(?::jsonb) wanted(id) LEFT JOIN sys_attribute_documents doc ON doc.record_id=wanted.id AND doc.account_id=? AND doc.account_incarnation=? AND doc.workspace_scope=? AND doc.entity=? WHERE '.$sql,
                    [json_encode(array_values(array_map(static fn (RecordRef $r): string => $r->id, $refs)), JSON_THROW_ON_ERROR),
                        $scope->accountId, $scope->accountIncarnation, $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId, $entity->value, ...$parameters]);
                $new = $this->filters->compile($scope, $schema, $filter);

                if ($new->policyWitness !== $plan->policyWitness || $new->recordIds !== $plan->recordIds || $new->mappingDigest !== $plan->mappingDigest) {
                    throw new RuntimeException('Field filter policy changed.');
                }
                $result = [];
                foreach ($rows as $row) {
                    if (! $row instanceof stdClass || ! is_string($row->id)) {
                        throw new RuntimeException('Invalid query result.');
                    }
                    $record = new RecordRef($entity->value, $row->id);

                    if ($allowed($record)) {
                        $result[] = $record;
                    }
                }

                return $result;
            });
        });
    }

    /** @return array{string,list<string>} */
    private function predicate(FilterPlan $plan, string $slot, string $type): array
    {
        $right = $plan->operand->canonicalJson ?? 'null';
        $op = $plan->operator;

        if ($op === FieldOperator::IsAbsent || $op === FieldOperator::IsNull) {
            return [$op === FieldOperator::IsAbsent ? '(doc.slots -> ?::text) IS NULL' : "(doc.slots -> ?::text) = 'null'::jsonb", [$slot]];
        }

        if ($op === FieldOperator::Eq || $op === FieldOperator::In) {
            return [$op === FieldOperator::Eq ? 'doc.slots -> ?::text = ?::jsonb' : 'doc.slots -> ?::text IN (SELECT value FROM jsonb_array_elements(?::jsonb))', [$slot, $right]];
        }

        if ($op === FieldOperator::Any || $op === FieldOperator::All) {
            if ($op === FieldOperator::All) {
                return ['(doc.slots -> ?::text) @> ?::jsonb', [$slot, $right]];
            }

            return ["EXISTS (SELECT 1 FROM jsonb_array_elements(CASE WHEN jsonb_typeof(doc.slots -> ?::text) = 'array' THEN doc.slots -> ?::text ELSE '[]'::jsonb END) v WHERE v.value IN (SELECT value FROM jsonb_array_elements(?::jsonb)))", [$slot, $slot, $right]];
        }

        if ($op === FieldOperator::Contains || $op === FieldOperator::FullText) {
            return [$op === FieldOperator::Contains ? "strpos(doc.slots ->> ?::text, ?::jsonb #>> '{}') > 0"
                : "to_tsvector('simple', doc.slots ->> ?::text) @@ plainto_tsquery('simple', ?::jsonb #>> '{}')", [$slot, $right]];
        }
        $cast = match ($type) {
            'int', 'decimal', 'money' => 'numeric', 'date' => 'date', 'datetime' => 'timestamptz',
            default => throw new RuntimeException('Field comparison unavailable.'),
        };
        $lhs = $type === 'money' ? "((doc.slots -> ?::text) ->> 'amount')::numeric" : "(doc.slots ->> ?::text)::$cast";
        $rhs = $type === 'money' ? "(?::jsonb ->> 'amount')::numeric" : "(?::jsonb #>> '{}')::$cast";

        if ($op === FieldOperator::Between) {
            $items = json_decode($right, flags: JSON_THROW_ON_ERROR);

            if (! is_array($items) || ! array_is_list($items) || count($items) !== 2) {
                throw new RuntimeException('Invalid range.');
            }

            return ["$lhs BETWEEN $rhs AND $rhs", [$slot, json_encode($items[0], JSON_THROW_ON_ERROR), json_encode($items[1], JSON_THROW_ON_ERROR)]];
        }
        $comparison = match ($op) {
            FieldOperator::Lt => '<', FieldOperator::Lte => '<=', FieldOperator::Gt => '>', default => '>=',
        };

        return ["$lhs $comparison $rhs", [$slot, $right]];
    }
}
