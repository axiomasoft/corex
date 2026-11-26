<?php

declare(strict_types=1);

namespace CoreX\Search\Tests\Unit;

use Closure;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\FieldWitnesses;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordBatch;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecords;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\Search\Testing\SearchFixtures;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Contracts\Foundation\Application;
use LogicException;

final class QueryFixture implements ActiveGenerations, CandidateDriver, DataScopeAuthorizer, FieldUsePolicy, SearchProjectionContributor, SearchSource, TenantContextResolver
{
    public int $generation = 1;

    public int $revision = 1;

    public int $policyEpoch = 1;

    public int $factoryCalls = 0;

    public int $driverCalls = 0;

    public int $loads = 0;

    public int $seen = 0;

    public array $ids = ['a', 'b', 'c', 'deleted'];

    public array $rows = ['a' => ['name' => 'Pump', 'paid' => true, 'internal' => 'private'], 'b' => ['name' => 'Pipe', 'paid' => false], 'c' => ['name' => 'Valve', 'paid' => true]];

    public array $indexed;

    public array $deniedIds = [];

    public array $deniedReads = [];

    public array $deniedUses = [];

    public array $corpora = [];

    public ?array $receivedCorpus = null;

    public ?Closure $tamper = null;

    public ?Closure $projectHook = null;

    public bool $allowCorpus = true;

    public readonly DataScope $scope;

    public readonly EntityHandle $entity;

    public function __construct()
    {
        $this->indexed = $this->rows;
        $this->scope = CallerWorkScopeFixture::forContext($this->current(), 'user-a', str_repeat('a', 64));
        $this->entity = new EntityHandle('equipment');
    }

    public function bind(Application $app): void
    {
        $app['config']->set('corex-search.driver', 'fixture');
        $app['config']->set('corex-search.cursor_secret', str_repeat('secret', 8));
        $app->forgetInstance(SearchOptions::class);
        foreach ([TenantContextResolver::class, DataScopeAuthorizer::class, FieldUsePolicy::class, SearchProjectionContributor::class, SearchSource::class, ActiveGenerations::class] as $port) {
            $app->instance($port, $this);
        }
        CallerWorkScopeFixture::bind($app, $this->scope);
        $app->make(SearchDriverRegistry::class)->register('fixture', function (): CandidateDriver {
            $this->factoryCalls++;

            return $this;
        });
    }

    public function current(): TenantContext
    {
        return new TenantContext(new AccountRef('account', 'account', 'active', [], []));
    }

    public function active(DataScope $scope, EntityHandle $entity): ?IndexTarget
    {
        if ($this->generation < 1) {
            return null;
        }

        return new IndexTarget($scope, $entity, 'fixture', 'primary', $this->generation,
            IndexTarget::uidFor($scope, $entity, 'fixture', 'primary', $this->generation), $this->describe(new ProjectionContext($scope, $entity))->revision);
    }

    public function capabilities(): DriverCapabilities
    {
        return new DriverCapabilities(true, true, true, $this->allowCorpus);
    }

    public function candidates(CandidateRequest $request): CandidatePage
    {
        $this->driverCalls++;
        $this->receivedCorpus = $request->recordIds;
        $offset = (int) $request->position;
        $ids = $request->recordIds === null ? $this->ids : array_values(array_intersect($this->ids, $request->recordIds));

        if ($request->text !== null) {
            $ids = array_values(array_filter($ids, fn (string $id): bool => array_any($request->searchFields, fn (string $field): bool => stripos($this->indexed[$id][$field] ?? '', (string) $request->text) !== false)));
        }
        $batch = array_slice($ids, $offset, $request->limit);
        $result = [];
        foreach ($batch as $id) {
            $ref = new RecordRef($request->generation->entity->value, $id);
            $witness = $request->text === null ? null : FieldWitnesses::capture(
                new ProjectedValues($this->indexed[$id]), new ProjectionSelection($request->searchFields));
            $item = new Candidate($request->generation, $ref, $request->generation->documentId($ref), $witness);
            $result[] = $this->tamper === null ? $item : ($this->tamper)($item);
        }
        $this->seen += count($batch);

        return new CandidatePage($result, $offset + count($batch) < count($ids) ? (string) ($offset + count($batch)) : null);
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $key = $request->use->value.'|'.implode(',', $request->fields->fields);

        if (in_array($key, $this->deniedUses, true)) {
            return FieldUsePlan::denied();
        }
        $witness = 'acl-'.$this->policyEpoch;

        return isset($this->corpora[$key]) ? FieldUsePlan::scoped($witness, $this->corpora[$key]) : FieldUsePlan::unconditional($witness);
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        return new DataScopeAccessDecision(! in_array($record->id, $this->deniedIds, true) &&
            ($use !== FieldUse::Read || ! array_intersect($fields->fields, $this->deniedReads)), 'test.decision');
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        return new ProjectionFields([
            new ProjectionField('name', FieldType::Text, FieldUse::cases()),
            new ProjectionField('paid', FieldType::Boolean, FieldUse::cases()),
        ], hash('sha256', 'mapping-'.$this->revision));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        if ($this->projectHook !== null) {
            ($this->projectHook)($this);
        }

        return new ProjectedValues([]);
    }

    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        throw new LogicException('Unexpected index scan.');
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        $this->loads++;
        $rows = [];
        foreach ($records->records as $ref) {
            if (isset($this->rows[$ref->id])) {
                $rows[] = SearchFixtures::record($ref, $this->rows[$ref->id]);
            }
        }

        return new SourceRecords($rows);
    }
}
