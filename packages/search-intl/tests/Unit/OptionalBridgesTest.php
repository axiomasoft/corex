<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Tests\Unit;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Intl\Currency\Contracts\CurrencyMetadata;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Contracts\TranslationReader;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Intl\Translation\Data\TranslationResult;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Data\Candidate;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\CurrentSearchRecord;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\Search\Testing\SearchFixtures;
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use CoreX\SearchIntl\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\Internal\LocalizedSearchHydrator;
use CoreX\SearchIntl\Internal\LocalizedSearchSchema;
use CoreX\SearchIntl\Internal\TranslationRevision;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\SearchIntl\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;

final class OptionalBridgesTest extends TestCase
{
    private function scope(): DataScope
    {
        return DataScope::fromTenantContext(
            context: new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])),
            connectionIdentity: 'sqlite::memory:',
            actorId: 'reader',
            restoreEpoch: str_repeat('a', 64),
        );
    }

    public function test_opt_01_keeps_intl_search_disabled_without_eager_bridge_registration(): void
    {
        $app = new Application;
        $app->instance('config', new Repository([
            'corex-search-intl' => ['enabled' => false],
        ]));

        new SearchIntlServiceProvider($app)->register();

        expect($app->bound(SavedViewLabelReader::class))->toBeFalse()
            ->and($app->bound(LocalizedFieldProjector::class))->toBeFalse();
    }

    public function test_opt_02_projects_localized_searchable_columns_without_meili_only_attributes(): void
    {
        $base = new class implements SearchSchema
        {
            public function describe(DataScope $scope, string $entity): SearchSnapshot
            {
                return new SearchSnapshot(
                    entity: $entity,
                    schemaHash: str_repeat('b', 64),
                    table: 'equipment',
                    idColumn: 'id',
                    searchableColumns: ['code'],
                    filterColumns: [],
                    resultColumns: ['id', 'code'],
                );
            }
        };

        $schema = new LocalizedSearchSchema(
            base: $base,
            bindings: [new LocalizedFieldBinding(column: 'title', declaredField: 'equipment.title', schemaHash: str_repeat('c', 64))],
            localeTag: 'ru',
        );

        $snapshot = $schema->describe(scope: $this->scope(), entity: 'equipment');

        expect($snapshot->searchableColumns)->toContain('title__ru')
            ->and($snapshot->searchableColumns)->toContain('code')
            ->and($snapshot->schemaHash)->not->toBe(str_repeat('b', 64));
    }

    public function test_opt_03_resolves_saved_view_labels_without_changing_grammar_metadata(): void
    {
        $reader = $this->app->make(SavedViewLabelReader::class);
        $locale = $this->app->make(LocaleResolver::class)->resolve(
            inputs: new LocaleInputs(projectedUserTag: 'ru', accountSettingsTag: null, requestContextKey: 'view-1'),
            policy: $this->app->make(LocalePolicy::class),
        );

        $result = $reader->read(
            localizedName: ['en' => 'Open tasks', 'ru' => 'Открытые задачи'],
            locale: $locale,
        );

        expect($result->status)->toBe('found')
            ->and($result->value)->toBe('Открытые задачи')
            ->and($result->usedLocale)->toBe('ru');
    }

    public function test_opt_06_records_translation_revision_changes_and_rejects_stale_hydrate_results(): void
    {
        $revision = new TranslationRevision;
        $before = $revision->compute(['en' => 'Pump', 'ru' => 'Насос'], 'saved-view/1');
        $after = $revision->compute(['en' => 'Pump', 'ru' => 'Насос обновленный'], 'saved-view/1');

        expect($before)->not->toBe($after);

        $schema = new SearchSnapshot(
            entity: 'equipment',
            schemaHash: str_repeat('d', 64),
            table: 'equipment',
            idColumn: 'id',
            searchableColumns: ['title'],
            resultColumns: ['id', 'title'],
        );
        $hydrator = SearchFixtures::policyHydrator(
            static fn (): CurrentSearchRecord => new CurrentSearchRecord(
                record: new RecordRef(entity: 'equipment', id: 'eq1'),
                schemaHash: $schema->schemaHash,
                revision: str_repeat('e', 64),
                fields: ['id' => 'eq1', 'title' => 'Late translation'],
            ),
            new CallableDataScopeAuthorizer(
                static fn (DataScope $_scope, string $entity, string $subjectId): bool => $subjectId === 'eq1',
            ),
        );

        $staleSchema = new SearchSnapshot(
            entity: 'equipment',
            schemaHash: str_repeat('f', 64),
            table: 'equipment',
            idColumn: 'id',
            searchableColumns: ['title'],
            resultColumns: ['id', 'title'],
        );

        $page = $hydrator->authorize(
            scope: $this->scope(),
            candidates: new CandidatePage(candidates: [new Candidate(new RecordRef(entity: 'equipment', id: 'eq1'))], nextAfterId: null, truncated: false),
            schema: $staleSchema,
        );

        expect($page->records)->toBe([]);
    }

    public function test_opt_07_read_descriptors_stay_read_only_without_config_mutation_or_network(): void
    {
        $metadata = $this->app->make(CurrencyMetadata::class);

        expect($this->app->make(LocaleResolver::class))->toBeInstanceOf(LocaleResolver::class)
            ->and($metadata->get('USD')->isoCode)->toBe('USD');

        $projector = $this->app->make(LocalizedFieldProjector::class);
        $result = $projector->project(
            text: new LocalizedText(declaredField: 'equipment.title', schemaHash: str_repeat('1', 64), map: ['en' => 'Pump', 'en-US' => 'Pump']),
            locale: $this->app->make(LocaleResolver::class)->resolve(
                inputs: new LocaleInputs(projectedUserTag: 'en', accountSettingsTag: null, requestContextKey: 'desc-1'),
                policy: $this->app->make(LocalePolicy::class),
            ),
        );

        expect($result)->toBe('Pump');
    }

    public function test_opt_02_hydrates_localized_result_columns_through_translation_reader(): void
    {
        $locale = new LocaleResolution(
            requested: 'ru',
            used: 'ru',
            origin: 'user',
            fallbackChain: [],
            wasFallback: false,
            policyHash: str_repeat('5', 64),
            icuVersion: null,
        );
        $binding = new LocalizedFieldBinding(column: 'title', declaredField: 'equipment.title', schemaHash: str_repeat('2', 64));
        $schema = new SearchSnapshot(
            entity: 'equipment',
            schemaHash: str_repeat('3', 64),
            table: 'equipment',
            idColumn: 'id',
            searchableColumns: ['code', 'title__ru'],
            resultColumns: ['id', 'title__ru'],
        );
        $base = SearchFixtures::policyHydrator(
            static fn (): CurrentSearchRecord => new CurrentSearchRecord(
                record: new RecordRef(entity: 'equipment', id: 'eq1'),
                schemaHash: str_repeat('3', 64),
                revision: str_repeat('4', 64),
                fields: ['id' => 'eq1'],
            ),
            new CallableDataScopeAuthorizer(
                static fn (DataScope $_scope, string $entity, string $subjectId): bool => $subjectId === 'eq1',
            ),
        );
        $projector = new LocalizedFieldProjector(new class implements TranslationReader
        {
            public function read(LocalizedText $text, LocaleResolution $locale): TranslationResult
            {
                return new TranslationResult(
                    status: 'found',
                    value: 'Насос',
                    usedLocale: 'ru',
                    wasFallback: false,
                );
            }
        });
        $hydrator = new LocalizedSearchHydrator(
            base: $base,
            projector: $projector,
            bindings: [$binding],
            locale: $locale,
            loadLocalized: static fn (): LocalizedText => new LocalizedText(
                declaredField: 'equipment.title',
                schemaHash: str_repeat('2', 64),
                map: ['en' => 'Pump', 'ru' => 'Насос'],
            ),
        );

        $page = $hydrator->authorize(
            scope: $this->scope(),
            candidates: new CandidatePage(candidates: [new Candidate(new RecordRef(entity: 'equipment', id: 'eq1'))], nextAfterId: null, truncated: false),
            schema: $schema,
        );

        expect($page->records)->toBe([['id' => 'eq1', 'title__ru' => 'Насос']]);
    }
}
