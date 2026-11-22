<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Projection\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldProjectionSchema;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Enums\FieldUsePlanKind;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Projection\Contracts\ProjectionFilter;
use CoreX\Search\Projection\Data\ProjectionContext;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class FieldProjectionFilter implements ProjectionFilter
{
    public function __construct(
        private FieldCatalogue $catalogue,
        private FieldFilter $filter,
        private ProjectionContext $context,
        private FieldProjectionSchema $expected,
        private FieldId $field,
    ) {}

    public function normalize(array $values, FieldUsePlan $grant): array
    {
        $schema = $this->catalogue->snapshot(scope: $this->context->scope, entity: $this->context->entity);

        if ($schema->revision->value !== $this->expected->revision->value || $schema->mappingDigest !== $this->expected->mappingDigest) {
            throw SearchWitnessChanged::changed();
        }
        $single = count($values) === 1;

        try {
            $node = new FilterNode(field: $this->field, operator: $single ? FieldOperator::Eq : FieldOperator::In, operand: FieldInput::from($single ? $values[0] : $values));
            $plan = $this->filter->compile(scope: $this->context->scope, schema: $schema, filter: $node);
        } catch (FieldFailure $failure) {
            throw $failure->errorDescriptor()->code === CustomFieldsErrorCode::SchemaRevisionMismatch->value
                ? SearchWitnessChanged::changed() : SearchFeatureUnavailable::unsupported();
        } catch (InvalidArgumentException|JsonException) {
            throw SearchFeatureUnavailable::unsupported();
        }

        $records = $grant->kind === FieldUsePlanKind::ScopedPredicate ? $grant->recordIds : null;

        if ($plan->policyWitness !== $grant->witness || $plan->recordIds !== $records) {
            throw SearchWitnessChanged::changed();
        }
        $operand = $plan->operand ?? throw SearchFeatureUnavailable::unsupported();
        $decoded = json_decode($operand->canonicalJson, flags: JSON_THROW_ON_ERROR);
        $normalized = $single ? [$decoded] : $decoded;

        if (! is_array($normalized) || ! array_is_list($normalized) || count($normalized) !== count($values)) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $result = [];
        foreach ($normalized as $value) {
            if (! is_string($value) && ! is_int($value) && ! is_bool($value)) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $result[] = $value;
        }

        return $result;
    }
}
