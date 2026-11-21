<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
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
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordBatch;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecord;
use CoreX\Search\Projection\Data\SourceRecords;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Enums\StatisticStatus;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\RebuildInput;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Enums\SchemaScope;

require getcwd().'/vendor/autoload.php';

final class SearchProofOwner implements DataScopeAuthorizer, FieldUsePolicy, SearchProjectionContributor, SearchSource, WorkPrincipalAuthority
{
    private bool $indexing = true;

    private bool $customFieldVisible = true;

    public function __construct(private readonly RecordRef $record, private string $title, private readonly ?string $customField) {}

    public function readAsUser(): void
    {
        $this->indexing = false;
    }

    public function changeTitle(string $title): void
    {
        $this->title = $title;
    }

    public function hideCustomField(): void
    {
        $this->customFieldVisible = false;
    }

    public function current(TenantContext $context): ScopePrincipal
    {
        verifyFixture($context->isBoxed(), 'Expected a standalone fixture account.');

        return $this->indexing ? new ScopePrincipal('service', 'search-fixture-index') : new ScopePrincipal('user', 'search-fixture-reader');
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        $principal = $this->current($context);

        if ($scope->accountId !== $context->account->id || $scope->principalKind !== $principal->kind || $scope->principalId !== $principal->id) {
            throw new ScopeContextMismatch('The fixture principal was not authenticated.');
        }

        return $work();
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $allowed = ($this->customField !== null && $record->entity === 'fields.schema' && $record->id === $this->record->entity
                && $fields->fields === [] && in_array($use, [FieldUse::Read, FieldUse::Write], true))
            || ($record->entity === $this->record->entity && $record->id === $this->record->id
                && $this->permits(new FieldUseRequest($fields, $use)));

        return new DataScopeAccessDecision($allowed, 'fixture-record-policy-v1');
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        if ($entity->value !== $this->record->entity || ! $this->permits($request)) {
            return FieldUsePlan::denied();
        }

        return FieldUsePlan::unconditional('fixture-search-policy-v1');
    }

    private function permits(FieldUseRequest $request): bool
    {
        if ($this->customField !== null && $request->fields->fields === [$this->customField]) {
            return $this->customFieldVisible && in_array($request->use, [FieldUse::Read, FieldUse::Filter, FieldUse::Sort], true);
        }

        return array_diff($request->fields->fields, ['title']) === []
            && in_array($request->use, [FieldUse::Read, FieldUse::Search, FieldUse::Aggregate], true);
    }

    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        $records = $entity->value === $this->record->entity && $after === null ? [$this->record] : [];

        return new RecordBatch(new RecordRefs($records), null);
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        foreach ($records->records as $record) {
            if ($record->entity === $this->record->entity && $record->id === $this->record->id) {
                return new SourceRecords([new SourceRecord($record, hash('sha256', $this->title), new ProjectedValues(['title' => $this->title]))]);
            }
        }

        return new SourceRecords([]);
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        $fields = $context->entity->value === $this->record->entity
            ? [new ProjectionField('title', FieldType::Text, [FieldUse::Read, FieldUse::Search])]
            : [];

        return new ProjectionFields($fields, hash('sha256', $context->entity->value.'/title/v1'));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        return new ProjectedValues([]);
    }
}

final readonly class SearchProofBinding implements ControlStoreIsolation, PhysicalBindingVerifier
{
    public const string Deployment = 'installed-search-proof';

    public function __construct(private string $owner, private string $control) {}

    public function verify(ConnectionInterface|PhysicalBinding $binding, string|TenantContext $scope, ?ConnectionInterface $connection = null): void
    {
        if ($binding instanceof ConnectionInterface) {
            if ($scope !== self::Deployment || $this->owner === $this->control || self::identity($binding)['database'] !== $this->control) {
                throw new ScopeContextMismatch('The Search fixture control database is not independent.');
            }

            return;
        }

        if (! $scope instanceof TenantContext || $connection === null) {
            throw new ScopeContextMismatch('The Search fixture has no entered owner connection.');
        }
        $actual = self::identity($connection);

        if (! $scope->isBoxed() || $binding->deploymentId !== self::Deployment || $actual['database'] !== $this->owner
            || $binding->bindingId !== 'postgres-'.$actual['oid'] || $binding->restoreEpoch !== self::epoch($actual)) {
            throw new ScopeContextMismatch('The Search fixture is connected to the wrong physical database.');
        }
    }

    public static function identity(ConnectionInterface $connection): array
    {
        $row = $connection->selectOne('select oid::text as oid, current_database() as database, pg_backend_pid() as session, version() as backend from pg_database where datname = current_database()');
        verifyFixture($row !== null && str_starts_with((string) $row->backend, 'PostgreSQL '), 'The Search fixture requires PostgreSQL.');

        return (array) $row;
    }

    public static function epoch(array $identity): string
    {
        return hash('sha256', $identity['database'].':'.$identity['oid']);
    }
}

final class SearchProofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $fixture = $app->make('search-proof.fixture');
        $owner = searchFixtureDatabase('A');
        $control = searchFixtureDatabase('CONTROL');
        $database = [
            'driver' => 'pgsql', 'host' => getenv('PGSQL_HOST'), 'port' => getenv('PGSQL_PORT'),
            'username' => getenv('PGSQL_USERNAME'), 'password' => getenv('PGSQL_PASSWORD'),
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public',
        ];
        $app['config']->set([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [...$database, 'database' => $owner],
            'database.connections.search_proof_control' => [...$database, 'database' => $control],
            'corex.scope.control_connection' => 'search_proof_control',
            'corex.scope.deployment_id' => SearchProofBinding::Deployment,
            'corex-search.driver' => $fixture['driver'], 'corex-search.cluster' => 'fixture-cluster',
            'corex-search.cursor_secret' => hash('sha256', 'search-fixture-'.getenv('APP_KEY')),
            'cache.default' => 'array',
        ]);

        if (getenv('COREX_SCENARIO_DB_BUDGET') !== false) {
            $budget = searchFixtureDatabase('BUDGET');
            $app['config']->set([
                'database.connections.search_proof_budget' => [...$database, 'database' => $budget],
                'corex-operations.schema.tenant' => ['connection' => 'pgsql', 'driver' => 'pgsql', 'database' => $owner],
                'corex-operations.schema.budget' => ['connection' => 'search_proof_budget', 'driver' => 'pgsql', 'database' => $budget],
            ]);
        }

        $binding = new SearchProofBinding($owner, $control);
        $app->instance(ControlStoreIsolation::class, $binding);
        $app->scoped(PhysicalBindingVerifier::class, static fn (): SearchProofBinding => $binding);
        $app->instance(SearchProofOwner::class, new SearchProofOwner(
            new RecordRef($fixture['entity'], $fixture['record']), $fixture['title'], $fixture['enabled'] ? $fixture['field'] : null,
        ));
        foreach ([WorkPrincipalAuthority::class, DataScopeAuthorizer::class, FieldUsePolicy::class, SearchSource::class] as $contract) {
            $app->alias(SearchProofOwner::class, $contract);
        }
        $app->tag(SearchProofOwner::class, SearchProjectionContributor::class);
    }
}

function searchFixtureDatabase(string $role): string
{
    $database = getenv('COREX_SCENARIO_DB_'.$role);
    verifyFixture(is_string($database) && preg_match('/\Acorex_scenario_[a-f0-9]{16}_'.strtolower($role).'_test\z/', $database) === 1,
        'The Search verifier must provide a fresh private database.');

    return $database;
}

function verifyFixture(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['searchProofAssertions'] = ($GLOBALS['searchProofAssertions'] ?? 0) + 1;
}

try {
    $class = $argv[1] ?? '';
    $profile = $argv[2] ?? 'absent';
    verifyFixture(in_array($profile, ['absent', 'enabled', 'equipment'], true), 'Choose an installed Search proof profile.');
    verifyFixture(class_exists($class) && is_callable([$class, 'searchFixture']), 'The installed fixture Search entry point is unavailable.');
    $enabled = $profile === 'enabled';
    verifyFixture(InstalledVersions::isInstalled('axiomasoft/corex-search-custom-fields') === $enabled
        && InstalledVersions::isInstalled('axiomasoft/corex-custom-fields') === ($profile !== 'absent')
        && InstalledVersions::isInstalled('axiomasoft/corex-panels') === $enabled, 'The optional packages do not match the fixture profile.');
    $fixture = [...$class::searchFixture(), 'enabled' => $enabled];
    $app = Application::configure(basePath: getcwd())->withProviders([SearchProofServiceProvider::class])->withExceptions()->create();
    $app->instance('search-proof.fixture', $fixture);
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    if (($argv[3] ?? null) === 'compile') {
        $compiled = $kernel->call('modules:compile');
        $output = $kernel->output();
        verifyFixture($compiled === 0, $output);
        echo json_encode([
            'phase' => 'module-compile', 'profile' => $profile, 'compiler_output' => $output,
            'php' => PHP_VERSION, 'pid' => getmypid(), 'assertions' => $GLOBALS['searchProofAssertions'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
        exit(0);
    }
    $db = $app->make(DatabaseManager::class);
    $owner = SearchProofBinding::identity($db->connection());
    $control = SearchProofBinding::identity($db->connection('search_proof_control'));
    verifyFixture($owner['database'] === searchFixtureDatabase('A') && $control['database'] === searchFixtureDatabase('CONTROL')
        && $owner['database'] !== $control['database'], 'The Search fixture database isolation is invalid.');
    $budget = getenv('COREX_SCENARIO_DB_BUDGET') === false ? null : SearchProofBinding::identity($db->connection('search_proof_budget'));
    ScopeControlSchema::install($db->connection('search_proof_control'));
    $app->make(SchemaMigrator::class)->migrate(SchemaScope::Tenant);
    $context = $app->make(TenantContextResolver::class)->current();
    $app->make(DatabaseScopeControl::class)->register($context->account->id, new PhysicalBinding(
        SearchProofBinding::Deployment, 'postgres-'.$owner['oid'], 1, 'search-proof-incarnation', SearchProofBinding::epoch($owner),
    ));
    $scopes = $app->make(DataScopeFactory::class);
    $scope = $scopes->forCurrentPrincipal($context);
    $work = $app->make(WorkScopeRunner::class);
    $entity = new EntityHandle($fixture['entity']);
    verifyFixture($app->make(EntityRegistry::class)->has($entity->value), 'The compiled module registry does not include the fixture entity.');

    if ($enabled) {
        require __DIR__.'/field-search-compile.php';

        $compiled = $work->run($scope, static fn (): array => compileFieldSearch($app, $scope, $context, $entity, $fixture));
        echo json_encode([
            'phase' => 'field-search-compile', 'profile' => $profile, 'entity' => $entity->value,
            'php' => PHP_VERSION, 'pid' => getmypid(), 'owner' => $owner, 'control' => $control,
            ...$compiled, 'assertions' => $GLOBALS['searchProofAssertions'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
        exit(0);
    }
    $target = $work->run($scope, static function () use ($app, $scope, $entity, $fixture) {
        $driver = $app->make(IndexDriver::class);
        verifyFixture(get_class($driver) === $fixture['class'] && in_array($driver, iterator_to_array($app->tagged(IndexDriver::class)), true)
            && in_array($driver, iterator_to_array($app->tagged(CandidateDriver::class)), true), 'The installed fixture driver was not discovered under both SPI tags.');
        $rebuilds = $app->make(SearchRebuilds::class);
        $plan = $rebuilds->plan($scope, new RebuildInput($entity));
        $projection = $app->make(SearchProjectionContributor::class)->describe(new ProjectionContext($scope, $entity));
        verifyFixture(array_column($projection->fields, 'id') === ['title'] && $projection->revision === $plan->mappingDigest,
            'Base Search compilation did not use the provider-owned field set.');
        $ref = $rebuilds->start($scope, $plan->ref, new OperationKey('fixture-index', 1));
        $index = $app->make(IndexWorker::class);
        $status = $rebuilds->inspect($scope, $ref);
        for ($step = 0; $step < 12 && $status->state === 'scanning'; $step++) {
            $index->advance($scope, $entity, $status->destination);
            $status = $rebuilds->inspect($scope, $ref);
        }
        verifyFixture($status->state === 'catching-up', 'The installed external driver did not complete the index scan.');
        $worker = $app->make(RebuildWorker::class);
        $worker->barrier($scope, $ref);
        verifyFixture($worker->publish($scope, $ref)->state === 'published', 'The installed Search generation did not publish.');

        return $app->make(ActiveGenerations::class)->active($scope, $entity);
    });
    $source = $app->make(SearchProofOwner::class);
    $source->readAsUser();
    $reader = $scopes->forCurrentPrincipal($context);
    $search = $app->make(Search::class);
    $query = new SearchQuery(
        entity: $entity, text: 'pump', searchFields: ['title'], fields: ['title'], count: true,
    );
    $result = $search->query($reader, $query);
    $driver = $app->make($fixture['class']);
    verifyFixture($target !== null && $target->backend === $fixture['driver'] && $driver->queries === 1
        && count($result->hits) === 1 && $result->hits[0]->record->entity === $entity->value && $result->hits[0]->record->id === $fixture['record']
        && $result->hits[0]->projection->values === ['title' => $fixture['title']]
        && $result->count->status === StatisticStatus::Unavailable && $result->count->value === null,
        'Public Search did not return the installed driver record and scoped projection.');
    $firstQueryCalls = $driver->queries;
    $source->changeTitle('valve replacement');
    verifyFixture($search->query($reader, $query)->hits === [] && $driver->queries === $firstQueryCalls + 1,
        'Public Search did not reject stale indexed text using the current owner values.');

    echo json_encode([
        'phase' => 'search', 'profile' => $profile, 'entrypoint' => Search::class, 'backend' => $target->backend,
        'adapter' => get_class($driver), 'tagged' => true, 'uid' => $target->uid, 'generation' => $target->generation,
        'entity' => $entity->value, 'record' => $result->hits[0]->record->id, 'projection' => $result->hits[0]->projection->values,
        'count' => $result->count->status->value, 'candidate_calls' => $firstQueryCalls, 'stale_text_rejected' => true,
        'compiled_fields' => ['title'], 'mapping' => $target->mappingDigest, 'assertions' => $GLOBALS['searchProofAssertions'],
        'php' => PHP_VERSION, 'pid' => getmypid(), 'owner' => $owner, 'control' => $control,
        ...($budget === null ? [] : ['budget' => $budget]),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
