<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Tests\Unit;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\FieldWitnesses;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecord;
use CoreX\Search\Projection\Data\SourceRecords;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\ExactCandidateDriver;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Data\SearchSort;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchIntl\Projection\Data\LocalizedFieldBinding;
use CoreX\SearchIntl\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Mockery;

final class LocalizedSearchAuthorizationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-search.driver', 'locale-test');
        $app['config']->set('corex-search.cursor_secret', str_repeat('secret', 8));
    }

    public function test_localized_predicates_require_read_on_the_shape_and_its_underlying_field(): void
    {
        $tenant = new TenantContext(new AccountRef('acct', 'Equipment', 'active', [], []));
        $scope = CallerWorkScopeFixture::forContext($tenant, 'reader', str_repeat('a', 64));
        $this->mock(TenantContextResolver::class)->shouldReceive('current')->andReturn($tenant);
        CallerWorkScopeFixture::bind($this->app, $scope);

        $entity = new EntityHandle('equipment');
        $record = new RecordRef($entity->value, 'equipment/custom-pk:01');
        $binding = new LocalizedFieldBinding('equipment.title', 'equipment.title', 'owner/v1', [
            FieldUse::Read, FieldUse::Search, FieldUse::Filter, FieldUse::Sort, FieldUse::Aggregate,
        ]);
        $locale = $this->app->make(LocaleResolver::class)->resolve(
            new LocaleInputs('ru-RU', null, 'equipment-search'), $this->app->make(LocalePolicy::class),
        );
        $shape = $binding->shapeId($locale->used);
        $title = 'Насос';
        $owner = $this->mock(LocalizedSearchValues::class);
        $owner->shouldReceive('locales')->andReturn([$locale]);
        $owner->shouldReceive('fields')->andReturn([$binding]);
        $ownerLoads = 0;
        $owner->shouldReceive('load')->with(Mockery::type(ProjectionContext::class), $binding, $record)->andReturnUsing(
            static function () use (&$ownerLoads, $binding, $title): LocalizedText {
                $ownerLoads++;

                return new LocalizedText($binding->declaredField, $binding->schemaHash, ['ru-RU' => $title]);
            },
        );
        $descriptor = $this->app->make(SearchProjectionContributor::class)->describe(new ProjectionContext($scope, $entity))->fields[0];
        self::assertNotNull($descriptor->physicalId);

        $readDenied = $binding->fieldId;
        $planned = [];
        $this->mock(FieldUsePolicy::class)->shouldReceive('plan')->andReturnUsing(
            static function (DataScope $scope, EntityHandle $entity, FieldUseRequest $request) use (&$planned, &$readDenied): FieldUsePlan {
                $planned[] = $request->use->value.'|'.implode(',', $request->fields->fields);

                return $request->use === FieldUse::Read && $request->fields->fields === [$readDenied]
                    ? FieldUsePlan::denied()
                    : FieldUsePlan::unconditional('locale-test-acl');
            },
        );
        $this->mock(DataScopeAuthorizer::class)->shouldReceive('authorize')->andReturn(new DataScopeAccessDecision(true, 'locale-test-auth'));
        $this->mock(SearchSource::class)->shouldReceive('load')->with($scope, Mockery::on(static fn (RecordRefs $refs): bool => $refs->records === [$record]))
            ->andReturn(new SourceRecords([new SourceRecord($record, hash('sha256', $title), new ProjectedValues([]))]));

        $targetCalls = $candidateCalls = 0;
        $driver = Mockery::mock(ExactCandidateDriver::class);
        $this->app->instance(CandidateDriver::class, $driver);
        $driver->shouldReceive('inSnapshot')->never();
        $driver->shouldReceive('statistics')->never();
        $driver->shouldReceive('capabilities')->andReturn(new DriverCapabilities(text: true, filter: true, sort: true, scopedCorpus: true));
        $driver->shouldReceive('target')->andReturnUsing(
            static function (DataScope $scope, EntityHandle $entity, string $mapping) use (&$targetCalls): IndexTarget {
                $targetCalls++;

                return new IndexTarget($scope, $entity, 'locale-test', 'primary', 1,
                    IndexTarget::uidFor($scope, $entity, 'locale-test', 'primary', 1), $mapping);
            },
        );
        $driver->shouldReceive('candidates')->once()->andReturnUsing(
            static function (CandidateRequest $request) use (&$candidateCalls, $record, $descriptor, $title): CandidatePage {
                $candidateCalls++;
                self::assertSame([$descriptor->physicalId], $request->searchFields);
                $witness = FieldWitnesses::capture(new ProjectedValues([$descriptor->physicalId => $title]), new ProjectionSelection($request->searchFields));

                return new CandidatePage([new Candidate($request->generation, $record, $request->generation->documentId($record), $witness)], null);
            },
        );
        $search = $this->app->make(Search::class);
        $text = new SearchQuery($entity, text: $title, searchFields: [$shape], fields: [$shape]);
        $predicates = [
            FieldUse::Search->value => $text,
            FieldUse::Filter->value => new SearchQuery($entity, filters: [new SearchFilter($shape, [$title])]),
            FieldUse::Sort->value => new SearchQuery($entity, sort: [new SearchSort($shape)]),
            FieldUse::Aggregate->value => new SearchQuery($entity, facets: [$shape]),
        ];
        foreach ([$binding->fieldId, $shape] as $readDenied) {
            foreach ($predicates as $use => $query) {
                $planned = [];
                expect(fn () => $search->query($scope, $query))->toThrow(SearchFeatureUnavailable::class);
                expect($planned)->toContain($use.'|'.$binding->fieldId, $use.'|'.$shape, 'read|'.$binding->fieldId, 'read|'.$shape)
                    ->and($targetCalls)->toBe(0)
                    ->and($candidateCalls)->toBe(0)
                    ->and($ownerLoads)->toBe(0);
            }
        }

        $readDenied = null;
        $hits = $search->query($scope, $text)->hits;
        expect($hits)->toHaveCount(1)
            ->and($hits[0]->record)->toBe($record)
            ->and($hits[0]->projection->values)->toBe([$shape => $title])
            ->and($candidateCalls)->toBe(1)
            ->and($ownerLoads)->toBeGreaterThan(0);
    }
}
