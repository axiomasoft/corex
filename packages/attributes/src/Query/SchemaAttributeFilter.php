<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Query;

use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Attributes\Support\FilterNode;
use CoreX\Attributes\Support\FilterPlan;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use InvalidArgumentException;

final class SchemaAttributeFilter implements AttributeFilter
{
    public function __construct(private readonly DataScopeAuthorizer $authorizer) {}

    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan
    {
        if (! $this->authorizer->authorize(scope: $scope, entity: $schema->entity, subjectId: '', fields: array_column($schema->activeFields, 'code'), action: 'filter')->allowed) {
            throw new ScopeDenied('The current scope denies this attribute filter.');
        }

        $fields = array_fill_keys(array_column($schema->activeFields, 'code'), true);
        $this->validate($filter->tree, $fields, 0);

        return new FilterPlan(schemaHash: $schema->schemaHash, tree: $filter->tree);
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, true>  $fields
     */
    private function validate(array $node, array $fields, int $depth): void
    {
        if ($depth > 8 || ! isset($node['op']) || ! is_string($node['op'])) {
            throw new InvalidArgumentException('Filter node is malformed.');
        }

        if (! in_array($node['op'], ['eq', 'in', 'range', 'isNull', 'isMissing', 'containsAny'], true)) {
            throw new InvalidArgumentException('Filter operator is unsupported.');
        }

        $field = $node['field'] ?? null;

        if (! is_string($field) || ! isset($fields[$field])) {
            throw new InvalidArgumentException('Filter references an unknown active field.');
        }
    }
}
