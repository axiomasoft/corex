<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use CoreX\Data\Data\RecordRef;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LocalizedSearchContributor implements SearchProjectionContributor
{
    public function __construct(private LocalizedFieldProjector $projector, private ?LocalizedSearchValues $source) {}

    public function describe(ProjectionContext $context): ProjectionFields
    {
        $locale = $this->source?->locale($context);
        $fields = [];
        $metadata = [];

        if ($locale !== null) {
            foreach ($this->source->fields($context) as $binding) {
                $id = $this->fieldId($binding, $locale);
                $fields[] = new ProjectionField($id, FieldType::Text, $binding->uses);
                $metadata[$id] = [
                    $binding->declaredField, $binding->schemaHash,
                    array_map(static fn ($use): string => $use->value, $binding->uses),
                ];
            }
        }
        ksort($metadata, SORT_STRING);

        return new ProjectionFields($fields, hash('sha256', json_encode([
            $locale?->used, $locale?->fallbackChain, $locale?->policyHash, $metadata,
        ], JSON_THROW_ON_ERROR)));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        if ($this->source === null || $record->entity !== $context->entity->value) {
            return new ProjectedValues([]);
        }
        $locale = $this->source->locale($context);
        $values = [];

        foreach ($this->source->fields($context) as $binding) {
            $field = $this->fieldId($binding, $locale);

            if (! in_array($field, $selection->fields, true)) {
                continue;
            }
            $text = $this->source->load($context, $binding, $record);

            if ($text !== null && $text->declaredField === $binding->declaredField && $text->schemaHash === $binding->schemaHash) {
                $value = $this->projector->project($text, $locale);

                if ($value !== null) {
                    $values[$field] = $value;
                }
            }
        }

        return new ProjectedValues($values);
    }

    private function fieldId(LocalizedFieldBinding $binding, LocaleResolution $locale): string
    {
        return $binding->column.'__'.$locale->used;
    }
}
