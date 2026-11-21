<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\CustomFields\Values\Data\SetField;
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
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Messaging\Contracts\OutboxRelay;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Enums\SchemaScope;

require getcwd().'/vendor/autoload.php';

final class FieldProofAuthority implements DataScopeAuthorizer, FieldUsePolicy, WorkPrincipalAuthority
{
    public const string Deployment = 'installed-field-proof';

    public const string Record = '04000000-0000-7000-8000-000000000001';

    public const string DeniedPrincipal = '03000000-0000-7000-8000-000000000002';

    private string $actor = '03000000-0000-7000-8000-000000000001';

    private string $entity;

    /** @var list<string> */
    private array $fieldIds;

    public function __construct(array $fixture)
    {
        $this->entity = $fixture['entity'];
        $this->fieldIds = array_column($fixture['fields'], 'id');
    }

    public function deny(): void
    {
        $this->actor = self::DeniedPrincipal;
    }

    public function current(TenantContext $context): ScopePrincipal
    {
        if (! $context->isBoxed()) {
            throw new ScopeContextMismatch('Expected the standalone fixture account.');
        }

        return new ScopePrincipal('user', $this->actor);
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        if ($scope->accountId !== $context->account->id || $scope->principalKind !== 'user' || $scope->principalId !== $this->actor) {
            throw new ScopeContextMismatch('The fixture principal was not authenticated.');
        }

        return $work();
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $isSchema = $record->entity === 'fields.schema' && $record->id === $this->entity;
        $isRecord = $record->entity === $this->entity && $record->id === self::Record;
        $allowed = $this->permits($scope, $fields, $use) && ($isSchema || $isRecord);

        return new DataScopeAccessDecision($allowed, $allowed ? 'fixture-grant' : 'fixture-deny');
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        if ($entity->value !== $this->entity || ! $this->permits($scope, $request->fields, $request->use)) {
            return FieldUsePlan::denied();
        }

        return $request->use === FieldUse::Filter
            ? FieldUsePlan::scoped('fixture-record-grant-v1', [self::Record])
            : FieldUsePlan::unconditional('fixture-field-grant-v1');
    }

    private function permits(DataScope $scope, FieldSelection $fields, FieldUse $use): bool
    {
        return $this->actor !== self::DeniedPrincipal && $scope->principalId === $this->actor
            && in_array($use, [FieldUse::Read, FieldUse::Write, FieldUse::Filter], true)
            && array_diff($fields->fields, $this->fieldIds) === [];
    }
}

final class FieldProofConnection
{
    public static function identity(ConnectionInterface $connection): array
    {
        $row = $connection->selectOne('select oid::text as oid, current_database() as database, pg_backend_pid() as session, version() as backend from pg_database where datname = current_database()');

        if ($row === null || ! str_starts_with((string) $row->backend, 'PostgreSQL ')) {
            throw new RuntimeException('The fixture requires PostgreSQL.');
        }

        return (array) $row;
    }

    public static function epoch(array $identity): string
    {
        return hash('sha256', $identity['database'].':'.$identity['oid']);
    }
}

final readonly class FieldProofControlIsolation implements ControlStoreIsolation
{
    public function __construct(private string $control, private string $owner) {}

    public function verify(ConnectionInterface $control, string $deploymentId): void
    {
        if ($deploymentId !== FieldProofAuthority::Deployment || $this->owner === $this->control
            || FieldProofConnection::identity($control)['database'] !== $this->control) {
            throw new ScopeContextMismatch('The fixture control database is not independent.');
        }
    }
}

final readonly class FieldProofPhysicalBinding implements PhysicalBindingVerifier
{
    public function __construct(private string $database) {}

    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        $actual = FieldProofConnection::identity($connection);

        if (! $context->isBoxed() || $binding->deploymentId !== FieldProofAuthority::Deployment
            || $actual['database'] !== $this->database || $binding->bindingId !== 'postgres-'.$actual['oid']
            || $binding->restoreEpoch !== FieldProofConnection::epoch($actual)) {
            throw new ScopeContextMismatch('The fixture is connected to the wrong physical database.');
        }
    }
}

final class FieldProofProvider extends ServiceProvider
{
    public function register(): void
    {
        $owner = tempDatabase('A');
        $control = tempDatabase('CONTROL');
        $this->app['config']->set([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => postgresConnection($owner),
            'database.connections.field_proof_control' => postgresConnection($control),
            'corex.scope.control_connection' => 'field_proof_control',
            'corex.scope.deployment_id' => FieldProofAuthority::Deployment,
            'cache.default' => 'array',
        ]);

        if (getenv('COREX_SCENARIO_DB_BUDGET') !== false) {
            $budget = tempDatabase('BUDGET');
            $this->app['config']->set([
                'database.connections.field_proof_budget' => postgresConnection($budget),
                'corex-operations.schema.tenant' => ['connection' => 'pgsql', 'driver' => 'pgsql', 'database' => $owner],
                'corex-operations.schema.budget' => ['connection' => 'field_proof_budget', 'driver' => 'pgsql', 'database' => $budget],
            ]);
        }
        $this->app->singleton(
            FieldProofAuthority::class,
            static fn (Application $app): FieldProofAuthority => new FieldProofAuthority($app->make('field-proof.fixture')),
        );
        $this->app->instance(ControlStoreIsolation::class, new FieldProofControlIsolation($control, $owner));
        $this->app->instance(PhysicalBindingVerifier::class, new FieldProofPhysicalBinding($owner));
        foreach ([WorkPrincipalAuthority::class, DataScopeAuthorizer::class, FieldUsePolicy::class] as $contract) {
            $this->app->alias(FieldProofAuthority::class, $contract);
        }
    }
}

function tempDatabase(string $role): string
{
    $database = getenv('COREX_SCENARIO_DB_'.$role);

    if (! is_string($database) || preg_match('/\Acorex_scenario_[a-f0-9]{16}_'.strtolower($role).'_test\z/D', $database) !== 1) {
        throw new RuntimeException('The verifier must provide a fresh private PostgreSQL database.');
    }

    return $database;
}

function postgresConnection(string $database): array
{
    return [
        'driver' => 'pgsql',
        'host' => getenv('PGSQL_HOST'),
        'port' => getenv('PGSQL_PORT'),
        'database' => $database,
        'username' => getenv('PGSQL_USERNAME'),
        'password' => getenv('PGSQL_PASSWORD'),
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
    ];
}

function requireWitness(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function exerciseFields(Application $app, DataScope $scope, array $fixture): array
{
    $entity = new EntityHandle($fixture['entity']);
    $record = new RecordRef($fixture['entity'], FieldProofAuthority::Record);
    $model = $fixture['model'];
    $model::query()->forceCreate([(new $model)->getKeyName() => $record->id, 'name' => 'Pump Alpha']);

    $catalogue = $app->make(FieldCatalogue::class);
    $values = $app->make(FieldValues::class);
    $schema = $catalogue->snapshot($scope, $entity);
    $selection = new FieldSelection(array_column($fixture['fields'], 'id'));
    $operations = [];
    foreach ($fixture['fields'] as $row) {
        $field = $schema->field($row['key']);
        requireWitness($field->id->value === $row['id'] && $field->type->value === $row['type']
            && $field->owner === $fixture['owner'] && $field->codecVersion === 1, 'The installed active field declaration changed.');
        $operations[] = new SetField($field->id, FieldInput::from($row['input']));
    }
    $patch = new FieldPatch($operations, $schema->revision, DocumentRevision::absent());
    $token = $patch->token($scope, $record, 'installed-fixture-write');
    $receipt = $values->update($scope, $record, $patch, $token);
    $again = $values->update($scope, $record, $patch, $token);
    requireWitness($receipt->messageId === $again->messageId
        && $receipt->valueRevision->value === $again->valueRevision->value, 'Replay changed the durable field receipt.');

    $one = $values->read($scope, $record, $selection);
    $many = $values->readMany($scope, new RecordSelection([$record]), $selection);
    requireWitness(count($many->items) === 1 && $many->rejections === []
        && $one->valueRevision->value === $receipt->valueRevision->value
        && $many->items[0]->valueRevision->value === $one->valueRevision->value, 'Installed field reads disagree with the committed receipt.');
    $observed = [];
    foreach ($fixture['fields'] as $row) {
        $observed[$row['key']] = JsonDocuments::decode($one->values[$row['id']]->document);
        requireWitness($observed[$row['key']] === $row['expected']
            && $many->items[0]->values[$row['id']]->document->canonicalJson === $one->values[$row['id']]->document->canonicalJson, 'The installed codec changed its typed value.');
    }

    $filter = $app->make(FieldFilter::class);
    $node = new FilterNode($schema->field($fixture['filter_key'])->id, FieldOperator::Eq, FieldInput::from($fixture['filter_operand']));
    $plan = $filter->compile($scope, $schema, $node);
    requireWitness($plan->field->value === $node->field->value && $plan->operator === FieldOperator::Eq
        && $plan->operand !== null && JsonDocuments::decode($plan->operand) === $fixture['encoded_operand']
        && $plan->schemaRevision->value === $schema->revision->value && $plan->mappingDigest === $schema->mappingDigest
        && $plan->policyWitness === 'fixture-record-grant-v1' && $plan->recordIds === [$record->id], 'The installed filter did not apply the exact codec and authorization.');

    $relay = $app->make(OutboxRelay::class);
    $claims = $relay->lease('05000000-0000-7000-8000-000000000001', 10);
    requireWitness(count($claims) === 1, 'The committed replay emitted more than one outbox fact.');
    $claim = $claims[0];
    $relay->assertClaim($claim);
    $fact = $claim->envelope;
    $ids = $selection->fields;
    sort($ids, SORT_STRING);
    $payload = $fact->payload->toArray();
    $payloadIds = $payload['field_ids'] ?? [];
    sort($payloadIds, SORT_STRING);
    requireWitness($fact->envelopeVersion === 2 && $fact->contract() === 'fields.values.changed.v2'
        && $fact->messageId === $receipt->messageId && $fact->seal !== null
        && $fact->subject instanceof RecordRef && $fact->subject->entity === $record->entity && $fact->subject->id === $record->id
        && get_object_vars($fact->scope) === get_object_vars($scope)
        && count($payload) === 3 && $payloadIds === $ids
        && $payload['schema_revision'] === $schema->revision->value && $payload['value_revision'] === $receipt->valueRevision->value,
        'The sealed fact does not match the exact field write receipt.');

    return [
        'schema' => $schema,
        'filter' => $node,
        'proof' => [
            'entity' => $entity->value, 'values' => $observed, 'filter_operand' => $fixture['encoded_operand'],
            'policy_witness' => $plan->policyWitness, 'schema_revision' => $schema->revision->value,
            'fact' => $fact->contract(), 'message_id' => $fact->messageId, 'sealed' => true,
            'field_ids' => $ids,
        ],
    ];
}

try {
    $class = $argv[1] ?? '';
    $phase = $argv[2] ?? '';

    requireWitness(in_array($phase, ['compile', 'exercise'], true), 'Choose the compile or exercise phase.');

    if (! class_exists($class) || ! is_callable([$class, 'fieldFixture'])) {
        throw new RuntimeException('The installed fixture field entry point is unavailable.');
    }
    $fixture = $class::fieldFixture();
    $app = Application::configure(basePath: getcwd())->withProviders([FieldProofProvider::class])->withExceptions()->create();
    $app->instance('field-proof.fixture', $fixture);
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    if ($phase === 'compile') {
        requireWitness($kernel->call('modules:compile') === 0, 'Module compilation failed: '.$kernel->output());
        echo json_encode(['phase' => $phase, 'php' => PHP_VERSION, 'pid' => getmypid()], JSON_THROW_ON_ERROR), "\n";
        exit(0);
    }

    $db = $app->make(DatabaseManager::class);
    $owner = FieldProofConnection::identity($db->connection());
    $control = FieldProofConnection::identity($db->connection('field_proof_control'));
    requireWitness($owner['database'] === tempDatabase('A') && $control['database'] === tempDatabase('CONTROL')
        && $owner['database'] !== $control['database'], 'The fixture database isolation is invalid.');
    $budget = getenv('COREX_SCENARIO_DB_BUDGET') === false ? null : FieldProofConnection::identity($db->connection('field_proof_budget'));
    ScopeControlSchema::install($db->connection('field_proof_control'));
    $app->make(SchemaMigrator::class)->migrate(SchemaScope::Tenant);
    $context = $app->make(TenantContextResolver::class)->current();
    $app->make(DatabaseScopeControl::class)->register($context->account->id, new PhysicalBinding(
        FieldProofAuthority::Deployment, 'postgres-'.$owner['oid'], 1, 'field-proof-incarnation', FieldProofConnection::epoch($owner),
    ));
    $scopes = $app->make(DataScopeFactory::class);
    $scope = $scopes->forCurrentPrincipal($context);
    $app->make(Gate::class)->define('corex.lifecycle.enable', static fn (ScopePrincipal $actor, DataScope $checked, ModuleRef $target): bool => $checked == $scope
        && $actor->kind === $scope->principalKind && $actor->id === $scope->principalId && $target->package === $fixture['owner']);
    $work = $app->make(WorkScopeRunner::class);
    $witness = $work->run($scope, static function () use ($app, $scope, $fixture): array {
        $app->make(ModuleLifecycle::class)->enable($fixture['owner']);

        return exerciseFields($app, $scope, $fixture);
    });
    $app->make(FieldProofAuthority::class)->deny();
    $deniedScope = $scopes->forCurrentPrincipal($context);

    try {
        $app->make(FieldFilter::class)->compile($deniedScope, $witness['schema'], $witness['filter']);

        throw new LogicException('The denied fixture actor compiled a filter.');
    } catch (RuntimeException $error) {
        requireWitness($error->getMessage() === 'custom-fields.access-denied', 'Unexpected denied filter outcome: '.$error->getMessage());
    }

    echo json_encode([
        ...$witness['proof'], 'denied_filter' => true,
        'phase' => $phase, 'php' => PHP_VERSION, 'pid' => getmypid(), 'owner' => $owner, 'control' => $control,
        ...($budget === null ? [] : ['budget' => $budget]),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
