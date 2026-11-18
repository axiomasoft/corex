<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Query;

use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\FilterPlan;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SchemaAttributeFilter implements AttributeFilter
{
    public function __construct(private DataScopeAuthorizer $authorizer) {}

    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan
    {
        $codes = [];
        foreach ($schema->activeFields as $field) {
            $code = $field['code'] ?? null;

            if (! is_string($code)) {
                throw new InvalidArgumentException('Active field code must be a string.');
            }
            $codes[] = $code;
        }

        if (! $this->authorizer->authorize(scope: $scope, entity: $schema->entity, subjectId: '', fields: $codes, action: 'filter')->allowed) {
            throw new ScopeDenied('The current scope denies this attribute filter.');
        }

        $fields = array_fill_keys($codes, true);
        $this->validate(node: $filter->tree, fields: $fields, depth: 0);

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
