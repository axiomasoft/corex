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
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use CoreX\SearchIntl\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\Internal\LocalizedSearchContributor;
use CoreX\SearchIntl\Internal\TranslationRevision;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\SearchIntl\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;

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
        $owner = new class implements LocalizedSearchValues
        {
            public int $loads = 0;

            public string $schema = 'equipment/title/v1';

            public string $policy = 'ru-policy/1';

            public function locale(ProjectionContext $context): LocaleResolution
            {
                return new LocaleResolution('ru', 'ru', 'user', ['ru', 'en'], false, hash('sha256', $this->policy), null);
            }

            public function fields(ProjectionContext $context): array
            {
                return [
                    new LocalizedFieldBinding('title', 'equipment.title', 'equipment/title/v1'),
                    new LocalizedFieldBinding('notes', 'equipment.notes', 'equipment/notes/v1', [FieldUse::Read]),
                ];
            }

            public function load(ProjectionContext $context, LocalizedFieldBinding $field, RecordRef $record): ?LocalizedText
            {
                $this->loads++;

                return new LocalizedText(declaredField: $field->declaredField, schemaHash: $this->schema, map: [
                    'en' => 'Pump', 'ru' => 'Насос',
                ]);
            }
        };
        $app = $this->app;
        $app->instance(LocalizedSearchValues::class, $owner);
        $contributors = iterator_to_array($app->tagged(SearchProjectionContributor::class));
        $projection = $app->make(SearchProjectionContributor::class);
        $descriptor = $projection->describe($context);
        $record = new RecordRef('equipment', 'eq1');
        $selection = new ProjectionSelection(['title__ru']);

        expect($contributors)->toHaveCount(1)
            ->and($contributors[0])->toBeInstanceOf(LocalizedSearchContributor::class)
            ->and(array_column($descriptor->fields, 'id'))->toBe(['notes__ru', 'title__ru'])
            ->and($descriptor->fields[0]->type)->toBe(FieldType::Text)
            ->and($descriptor->fields[0]->uses)->toBe([FieldUse::Read])
            ->and($descriptor->fields[1]->uses)->toBe([FieldUse::Read, FieldUse::Search])
            ->and($projection->project($context, $record, $selection)->values)->toBe(['title__ru' => 'Насос'])
            ->and($owner->loads)->toBe(1)
            ->and($projection->project($context, new RecordRef('other', 'eq1'), $selection)->values)->toBe([])
            ->and($owner->loads)->toBe(1);

        $owner->schema = 'stale';
        $owner->policy = 'ru-policy/2';

        expect($projection->project($context, $record, $selection)->values)->toBe([])
            ->and($owner->loads)->toBe(2)
            ->and($projection->describe($context)->revision)->not->toBe($descriptor->revision);
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

    private function locale(string $tag): LocaleResolution
    {
        return $this->app->make(LocaleResolver::class)->resolve(
            inputs: new LocaleInputs(projectedUserTag: $tag, accountSettingsTag: null, requestContextKey: 'view-1'),
            policy: $this->app->make(LocalePolicy::class),
        );
    }
}
