<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Tests\Unit;

use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Intl\Currency\Contracts\CurrencyMetadata;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
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
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Internal\TranslationRevision;
use CoreX\SearchIntl\Projection\Data\LocalizedFieldBinding;
use CoreX\SearchIntl\Projection\Internal\LocalizedFieldContributor;
use CoreX\SearchIntl\Projection\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\SearchIntl\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

final class OptionalBridgesTest extends TestCase
{
    public function test_opt_01_keeps_intl_search_disabled_without_eager_bridge_registration(): void
    {
        $app = new Application;
        $app->instance('config', new Repository(['corex-search-intl' => ['enabled' => false]]));

        new SearchIntlServiceProvider($app)->register();

        expect($app->bound(SavedViewLabelReader::class))->toBeFalse()
            ->and($app->bound(LocalizedFieldProjector::class))->toBeFalse()
            ->and(iterator_to_array($app->tagged(SearchProjectionContributor::class)))->toBe([]);
    }

    public function test_opt_02_registers_and_projects_only_selected_localized_values(): void
    {
        $context = $this->context();
        $owner = new class($this->locale(null), $this->locale('rU-rU'), $this->locale('zh-hant-tw')) implements LocalizedSearchValues
        {
            public int $loads = 0;

            public ?RecordRef $lastRecord = null;

            public string $schema = 'equipment/title/v1';

            public bool $changeFallback = false;

            public bool $reverse = false;

            public bool $duplicate = false;

            public function __construct(private readonly LocaleResolution $default, private readonly LocaleResolution $russian, private readonly LocaleResolution $chinese) {}

            public function locales(ProjectionContext $context): array
            {
                $chinese = $this->changeFallback
                    ? new LocaleResolution(null, $this->chinese->used, 'app', ['ru-RU'], false, $this->chinese->policyHash, $this->chinese->icuVersion)
                    : $this->chinese;
                $locales = [$this->default, $this->russian, $chinese];

                return $this->reverse ? array_reverse($locales) : $locales;
            }

            public function fields(ProjectionContext $context): array
            {
                $fields = [
                    new LocalizedFieldBinding('field:equipment:title', 'equipment.title', 'equipment/title/v1'),
                    new LocalizedFieldBinding('notes', 'equipment.notes', 'equipment/notes/v1', [FieldUse::Read]),
                ];

                if ($this->duplicate) {
                    $fields[] = $fields[0];
                }

                return $this->reverse ? array_reverse($fields) : $fields;
            }

            public function load(ProjectionContext $context, LocalizedFieldBinding $field, RecordRef $record): ?LocalizedText
            {
                $this->loads++;
                $this->lastRecord = $record;

                return new LocalizedText(declaredField: $field->declaredField, schemaHash: $this->schema, map: [
                    'en' => 'Pump', 'ru-RU' => 'Насос',
                ]);
            }
        };
        $app = $this->app;
        $app->instance(LocalizedSearchValues::class, $owner);
        $contributors = iterator_to_array($app->tagged(SearchProjectionContributor::class));
        $projection = $app->make(SearchProjectionContributor::class);
        $descriptor = $projection->describe($context);
        $record = new RecordRef('equipment', 'equipment/custom-pk:01');
        $binding = $owner->fields($context)[0];
        $default = $binding->shapeId('en');
        $russian = $binding->shapeId('ru-RU');
        $chinese = $binding->shapeId('zh-Hant-TW');
        $selection = new ProjectionSelection([$chinese, $russian, $default]);

        expect($contributors)->toHaveCount(1)
            ->and($contributors[0])->toBeInstanceOf(LocalizedFieldContributor::class)
            ->and($russian)->toBe('localized:field:equipment:title:ru-RU')
            ->and(array_column($descriptor->fields, 'id'))->toBe([$default, $russian, $chinese, 'localized:notes:en', 'localized:notes:ru-RU', 'localized:notes:zh-Hant-TW'])
            ->and($descriptor->fields[0]->type)->toBe(FieldType::Text)
            ->and($descriptor->fields[0]->authorizationField)->toBe($binding->fieldId)
            ->and($descriptor->fields[0]->physicalId)->toMatch('/\Al_[a-f0-9]{60}\z/D')
            ->and($descriptor->fields[0]->uses)->toBe([FieldUse::Read, FieldUse::Search])
            ->and($descriptor->fields[3]->uses)->toBe([FieldUse::Read])
            ->and($projection->project($context, $record, $selection)->values)->toBe([$default => 'Pump', $russian => 'Насос', $chinese => 'Pump'])
            ->and($owner->lastRecord)->toBe($record)
            ->and($owner->loads)->toBe(1)
            ->and($projection->project($context, new RecordRef('other', 'eq1'), $selection)->values)->toBe([])
            ->and($owner->loads)->toBe(1);

        $owner->reverse = true;
        expect($projection->describe($context))->toEqual($descriptor);
        $owner->changeFallback = true;
        expect($projection->describe($context)->revision)->not->toBe($descriptor->revision)
            ->and($projection->project($context, $record, new ProjectionSelection([$chinese]))->values)->toBe([$chinese => 'Насос']);
        $owner->schema = 'stale';

        expect($projection->project($context, $record, $selection)->values)->toBe([])
            ->and($owner->loads)->toBe(3);

        $owner->duplicate = true;
        expect(fn () => $projection->describe($context))->toThrow(InvalidArgumentException::class, 'duplicate shape or physical key');

        $owner->duplicate = false;
        $app->instance('test.colliding-field', new readonly class($descriptor->fields[0]->physicalId) implements SearchProjectionContributor
        {
            public function __construct(private string $id) {}

            public function describe(ProjectionContext $context): ProjectionFields
            {
                return new ProjectionFields([
                    new ProjectionField($this->id, FieldType::Text, [FieldUse::Read]),
                ], hash('sha256', $this->id));
            }

            public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
            {
                return new ProjectedValues([]);
            }
        });
        $app->tag('test.colliding-field', SearchProjectionContributor::class);
        expect(fn () => $app->make(SearchProjectionContributor::class)->describe($context))
            ->toThrow(InvalidArgumentException::class, 'A physical projection field ID has multiple owners.');
    }

    public function test_long_stable_ids_have_bounded_distinct_semantic_shapes(): void
    {
        $binding = new LocalizedFieldBinding(str_repeat('a', 128), 'equipment.title', 'v1');
        $shape = $binding->shapeId('zh-Hant-TW');

        expect($shape)->toMatch('/\Alocalized::v1:[a-f0-9]{64}\z/D')
            ->and($binding->shapeId('zh-Hant-TW'))->toBe($shape)
            ->and($binding->shapeId('ru-RU'))->not->toBe($shape)
            ->and(fn () => $binding->shapeId('ru_RU'))->toThrow(InvalidArgumentException::class);
    }

    public function test_opt_03_resolves_saved_view_labels_without_changing_grammar_metadata(): void
    {
        $reader = $this->app->make(SavedViewLabelReader::class);
        $result = $reader->read(
            localizedName: ['en' => 'Open tasks', 'ru' => 'Открытые задачи'],
            locale: $this->locale('ru'),
        );

        expect($result->status)->toBe('found')
            ->and($result->value)->toBe('Открытые задачи')
            ->and($result->usedLocale)->toBe('ru');
    }

    public function test_opt_06_records_translation_revision_changes_and_ignores_map_order(): void
    {
        $revision = new TranslationRevision;
        $before = $revision->compute(['en' => 'Pump', 'ru' => 'Насос'], 'saved-view/1');

        expect($revision->compute(['ru' => 'Насос', 'en' => 'Pump'], 'saved-view/1'))->toBe($before)
            ->and($revision->compute(['en' => 'Pump', 'ru' => 'Насос обновленный'], 'saved-view/1'))->not->toBe($before);
    }

    public function test_opt_07_keeps_base_descriptors_available_without_a_localized_owner(): void
    {
        $metadata = $this->app->make(CurrencyMetadata::class);
        $projector = $this->app->make(LocalizedFieldProjector::class);
        $projection = $this->app->make(SearchProjectionContributor::class);

        expect($this->app->make(LocaleResolver::class))->toBeInstanceOf(LocaleResolver::class)
            ->and($metadata->get('USD')->isoCode)->toBe('USD')
            ->and($projection->describe($this->context())->fields)->toBe([])
            ->and($projector->project(
                text: new LocalizedText(declaredField: 'equipment.title', schemaHash: str_repeat('1', 64), map: ['en' => 'Pump', 'en-US' => 'Pump']),
                locale: $this->locale('en'),
            ))->toBe('Pump');
    }

    public function test_missing_translations_leave_the_saved_view_label_unavailable(): void
    {
        $reader = $this->app->make(SavedViewLabelReader::class);
        $result = $reader->read(localizedName: [], locale: $this->locale('ru'));

        expect($result->status)->toBe('invalid')->and($result->value)->toBeNull();
    }

    private function context(): ProjectionContext
    {
        return new ProjectionContext(
            CallerWorkScopeFixture::scope('acct', null, 'reader', str_repeat('a', 64)),
            new EntityHandle('equipment'),
        );
    }

    private function locale(?string $tag): LocaleResolution
    {
        return $this->app->make(LocaleResolver::class)->resolve(
            inputs: new LocaleInputs(projectedUserTag: $tag, accountSettingsTag: null, requestContextKey: 'view-1'),
            policy: $this->app->make(LocalePolicy::class),
        );
    }
}
