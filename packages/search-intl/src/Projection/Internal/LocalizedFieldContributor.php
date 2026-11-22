<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Projection\Internal;

use CoreX\Data\Data\RecordRef;
use CoreX\Intl\Locale\Contracts\LocaleValidation;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchIntl\Projection\Data\LocalizedFieldBinding;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LocalizedFieldContributor implements SearchProjectionContributor
{
    public function __construct(
        private LocalizedFieldProjector $projector,
        private LocaleValidation $locales,
        private ?LocalizedSearchValues $source,
    ) {}

    public function describe(ProjectionContext $context): ProjectionFields
    {
        $shapes = $this->shapes($context);
        $fields = [];
        $metadata = [];

        foreach ($shapes as $id => [$binding, $locale, $physical]) {
            $fields[] = new ProjectionField(
                id: $id, type: FieldType::Text, uses: $binding->uses,
                physicalId: $physical, authorizationField: $binding->fieldId, predicateRequiresRead: true,
            );
            $uses = array_column($binding->uses, 'value');
            sort($uses, SORT_STRING);
            $metadata[$id] = [
                $binding->fieldId, $locale->used, $physical, $binding->declaredField, $binding->schemaHash, $uses,
                $locale->fallbackChain, $locale->policyHash, $locale->icuVersion,
            ];
        }

        return new ProjectionFields(fields: $fields, revision: hash('sha256', json_encode(['corex.search-intl.projection.v1', $metadata], JSON_THROW_ON_ERROR)));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        if ($this->source === null || $record->entity !== $context->entity->value) {
            return new ProjectedValues([]);
        }
        $values = [];
        /** @var array<string, LocalizedText|null> $texts */
        $texts = [];

        foreach ($this->shapes($context) as $id => [$binding, $locale]) {
            if (! in_array($id, $selection->fields, true)) {
                continue;
            }

            if (! array_key_exists($binding->fieldId, $texts)) {
                $texts[$binding->fieldId] = $this->source->load(context: $context, field: $binding, record: $record);
            }
            $text = $texts[$binding->fieldId];

            if ($text !== null && $text->declaredField === $binding->declaredField && $text->schemaHash === $binding->schemaHash) {
                $value = $this->projector->project(text: $text, locale: $locale);

                if ($value !== null) {
                    $values[$id] = $value;
                }
            }
        }

        return new ProjectedValues($values);
    }

    /** @return array<string, array{LocalizedFieldBinding, LocaleResolution, string}> */
    private function shapes(ProjectionContext $context): array
    {
        if ($this->source === null) {
            return [];
        }
        $locales = $this->source->locales($context);
        foreach ($locales as $locale) {
            if ($this->locales->validate($locale->used) !== $locale->used) {
                throw new InvalidArgumentException('A localized Search shape requires a canonical supported locale.');
            }
        }
        $shapes = [];
        $physicalKeys = [];
        foreach ($this->source->fields($context) as $binding) {
            foreach ($locales as $locale) {
                $id = $binding->shapeId($locale->used);
                $tuple = ['corex.search-intl.field.v1', $binding->fieldId, $locale->used];
                $physical = 'l_'.substr(hash('sha256', json_encode($tuple, JSON_THROW_ON_ERROR)), 0, 60);

                if (isset($shapes[$id]) || isset($physicalKeys[$physical])) {
                    throw new InvalidArgumentException('Localized Search field mapping contains a duplicate shape or physical key.');
                }
                $shapes[$id] = [$binding, $locale, $physical];
                $physicalKeys[$physical] = true;
            }
        }
        ksort($shapes, SORT_STRING);

        return $shapes;
    }
}
