<?php

declare(strict_types=1);

namespace App\FieldSearch;

use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchIntl\Projection\Data\LocalizedFieldBinding;
use Illuminate\Support\Facades\DB;

final readonly class EquipmentLocaleSource implements LocalizedSearchValues
{
    public const string PUBLIC_ID = 'equipment-caption';

    public const string PRIVATE_ID = 'equipment-private-caption';

    public function __construct(private LocaleResolver $locales, private LocalePolicy $policy) {}

    /** @return list<LocaleResolution> */
    public function locales(ProjectionContext $context): array
    {
        return array_map(fn (?string $tag): LocaleResolution => $this->locales->resolve(new LocaleInputs($tag, null, 'equipment'), $this->policy),
            [null, 'ru-RU', 'zh-Hant-TW']);
    }

    public function fields(ProjectionContext $context): array
    {
        if ($context->entity->value !== EquipmentOwner::ENTITY) {
            return [];
        }
        $result = [];
        foreach ([self::PUBLIC_ID, self::PRIVATE_ID] as $id) {
            $key = 'equipment.'.$id;
            $result[] = new LocalizedFieldBinding(fieldId: $id, declaredField: $key,
                schemaHash: hash('sha256', $key.':1'), uses: [FieldUse::Read, FieldUse::Search, FieldUse::Filter, FieldUse::Sort, FieldUse::Aggregate]);
        }

        return $result;
    }

    public function load(ProjectionContext $context, LocalizedFieldBinding $field, RecordRef $record): ?LocalizedText
    {
        if ($record->entity !== EquipmentOwner::ENTITY || ! EquipmentOwner::records($context->scope)->where('asset_key', $record->id)->exists()) {
            return null;
        }
        $encoded = DB::table('scenario_equipment_locales')->where(['asset_key' => $record->id, 'field_id' => $field->fieldId])->value('translations');

        return is_string($encoded) ? new LocalizedText($field->declaredField, $field->schemaHash, json_decode($encoded, true, flags: JSON_THROW_ON_ERROR)) : null;
    }

    public static function shape(string $id, string $locale): string
    {
        return 'localized:'.$id.':'.$locale;
    }
}
