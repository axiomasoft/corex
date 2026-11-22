<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Query\Internal;

use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Query\Data\FilterPlan;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldReferences;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SchemaFieldFilter implements FieldFilter
{
    public function __construct(private WorkScopeRunner $runner, private FieldCatalogue $catalogue, private FieldUsePolicy $policy, private FieldTypes $types, private DataScopeAuthorizer $access) {}

    public function compile(DataScope $scope, FieldSchemaSnapshot $schema, FilterNode $filter): FilterPlan
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $schema, $filter): FilterPlan {
            $current = $this->catalogue->snapshot(scope: $scope, entity: $schema->entity);

            if ($current->revision->value !== $schema->revision->value || $current->mappingDigest !== $schema->mappingDigest) {
                throw new FieldFailure(CustomFieldsErrorCode::SchemaRevisionMismatch);
            }
            $field = $current->fields[$filter->field->value] ?? throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            $type = $this->types->resolve(type: $field->type, version: $field->codecVersion);
            $use = $this->policy->plan(scope: $scope, entity: $current->entity, request: new FieldUseRequest(fields: new FieldSelection([$field->id->value]), use: FieldUse::Filter));

            if ($use->kind === FieldUsePlanKind::Denied || $use->witness === null || ! $field->permits(FieldUse::Filter, $type->definition())) {
                throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            }

            if (! in_array($filter->operator, $type->operators($field)->operators, true)) {
                throw new InvalidArgumentException('Unsupported field operator.');
            }

            return new FilterPlan(entity: $current->entity, field: $field->id, operator: $filter->operator, operand: $this->operand(scope: $scope, filter: $filter, type: $type, field: $field),
                schemaRevision: $current->revision, mappingDigest: $current->mappingDigest, scopeFingerprint: hash('sha256', json_encode([DataScope::VERSION, $scope], JSON_THROW_ON_ERROR)), policyWitness: $use->witness,
                recordIds: $use->kind === FieldUsePlanKind::ScopedPredicate ? $use->recordIds : null);
        });
    }

    private function operand(DataScope $scope, FilterNode $filter, FieldType $type, FieldDefinition $field): ?JsonDocument
    {
        if ($filter->operand === null) {
            return null;
        }
        $encode = function (FieldInput $input) use ($scope, $type, $field): JsonDocument {
            $value = $type->normalize(input: $input, field: $field);

            if (! FieldReferences::visible(access: $this->access, scope: $scope, field: $field, value: $value)) {
                throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            }

            return $type->definition()->codec->encode($value);
        };

        if (in_array($filter->operator, [FieldOperator::Any, FieldOperator::All], true)) {
            $this->requireCollection($filter->operand->document);
            $encoded = $encode($filter->operand);
            $this->requireCollection($encoded);

            return $encoded;
        }

        if (! in_array($filter->operator, [FieldOperator::In, FieldOperator::Between], true)) {
            return $encode($filter->operand);
        }
        $list = json_decode($filter->operand->document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        if (! is_array($list) || $list === [] || count($list) > 256 || ($filter->operator === FieldOperator::Between && count($list) !== 2)) {
            throw new InvalidArgumentException('Invalid field filter bounds.');
        }
        $encoded = [];
        foreach ($list as $item) {
            $encoded[] = $encode(new FieldInput(new JsonDocument(json_encode($item, JSON_THROW_ON_ERROR))))->canonicalJson;
        }

        return new JsonDocument('['.implode(',', $encoded).']');
    }

    private function requireCollection(JsonDocument $operand): void
    {
        // JsonDocument guarantees valid JSON. Inspect the outer kind without decoding numeric elements.
        if (! str_starts_with(ltrim($operand->canonicalJson), '[')) {
            throw new InvalidArgumentException('Field collection operators require an array operand.');
        }
    }
}
