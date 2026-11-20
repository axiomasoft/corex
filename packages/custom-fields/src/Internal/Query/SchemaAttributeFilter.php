<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Query;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\FilterPlan;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SchemaAttributeFilter implements AttributeFilter
{
    public function __construct(private FieldUsePolicy $policy, private CurrentWorkScope $current) {}

    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan
    {
        $this->current->assert($scope);
        $fields = [];

        foreach ($schema->activeFields as $field) {
            $code = $field['code'] ?? null;

            if (! is_string($code)) {
                throw new InvalidArgumentException('Active field code must be a string.');
            }

            $id = $field['id'] ?? AttributeId::legacy($schema->entity, $code)->value;

            if (! is_string($id)) {
                throw new InvalidArgumentException('Active field identity must be a string.');
            }
            $fields[$code] = $id;
        }

        $field = $this->validate($filter->tree, $fields);
        $plan = $this->policy->plan($scope, new EntityHandle($schema->entity), new FieldUseRequest(new FieldSelection([$field]), FieldUse::Filter));

        if ($plan->kind !== FieldUsePlanKind::Unconditional || $plan->witness === null) {
            throw new ScopeDenied('The current scope denies this attribute filter.');
        }

        return new FilterPlan(
            schemaHash: $schema->schemaHash,
            tree: $filter->tree,
            scopeFingerprint: hash('sha256', json_encode([DataScope::VERSION, $scope], JSON_THROW_ON_ERROR)),
            policyWitness: $plan->witness,
        );
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, string>  $fields
     */
    private function validate(array $node, array $fields): string
    {
        if (! isset($node['op']) || ! is_string($node['op'])) {
            throw new InvalidArgumentException('Filter node is malformed.');
        }

        if (! in_array($node['op'], ['eq', 'in', 'range', 'isNull', 'isMissing', 'containsAny'], true)) {
            throw new InvalidArgumentException('Filter operator is unsupported.');
        }

        $field = $node['field'] ?? null;

        if (! is_string($field) || ! isset($fields[$field])) {
            throw new InvalidArgumentException('Filter references an unknown active field.');
        }

        return $fields[$field];
    }
}
