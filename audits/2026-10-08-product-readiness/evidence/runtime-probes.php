<?php

declare(strict_types=1);

// Read-only audit reproductions. All files live in tmp; SQL uses sqlite::memory:.
require dirname(__DIR__, 3).'/vendor/autoload.php';

use CoreX\Config\Data\SecretRef;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Database\Internal\TaggedSchemaSets;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Modules\Internal\Compiler\InstalledPackages;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Laravel\LifecycleRegistrar;
use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Internal\BudgetAdmission;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\ResolvedContext;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;

$temporary = sys_get_temp_dir().'/corex-audit-probes-'.bin2hex(random_bytes(8));
mkdir($temporary.'/vendor/composer', 0700, true);
$previous = Illuminate\Container\Container::getInstance();
$application = new Application($temporary);
Illuminate\Container\Container::setInstance($application);
$shapeResults = [];
try {
    foreach (['null', '"invalid-root"', '{"packages":null}', '{"packages":"invalid-list"}'] as $json) {
        $path = $temporary.'/vendor/composer/installed.json';
        file_put_contents($path, $json);
        $wiring = new ReflectionMethod(LifecycleRegistrar::class, 'moduleWiring')->invoke(new LifecycleRegistrar, new TaggedSchemaSets([]));
        $shapeResults[] = [
            'input' => $json,
            'modules' => count(new ModuleDiscovery(installedJsonPath: $path)->discover()),
            'packages' => count(InstalledPackages::discover($path)),
            'code_versions' => count($wiring['codeVersions']),
            'exception' => null,
        ];
    }
} finally {
    Illuminate\Container\Container::setInstance($previous);
    unlink($temporary.'/vendor/composer/installed.json');
    rmdir($temporary.'/vendor/composer');
    rmdir($temporary.'/vendor');
    rmdir($temporary);
}

$capture = new class implements OperationBudget
{
    public array $scopes = [];

    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation
    {
        $this->scopes[] = $scope;

        return new class implements BudgetReservation
        {
            public function settle(ActualCost $cost): void {}

            public function reject(): void {}
        };
    }
};
$estimator = new class implements OperationCostEstimator
{
    public function estimate(Operation $operation, CostInput $input): EstimatedCost
    {
        return new EstimatedCost(units: 1, recipeHash: hash('sha256', 'audit'));
    }
};
$admission = new BudgetAdmission(budget: $capture, estimator: $estimator, installationId: 'audit-install');
$operation = new Operation(module: 'audit', id: 'audit.read', version: 'v1', method: 'GET', path: '/audit', ability: 'read',
    handler: CoreX\Operations\Testing\StubOperationHandler::class, requestSchema: JsonDocuments::capture([]),
    responseSchema: JsonDocuments::capture([]), inputRules: [], successStatus: 200);
foreach ([PrincipalType::User, PrincipalType::Agent] as $type) {
    $principal = new class($type) implements Principal
    {
        public function __construct(private PrincipalType $type) {}

        public function principalType(): PrincipalType { return $this->type; }

        public function principalId(): string { return '5'; }
    };
    $context = new ResolvedContext(tenantContext: new TenantContext(new AccountRef(id: 'account', slug: 'audit', status: 'active', features: [], limits: [])),
        authenticatedPrincipal: $principal, requestMetadata: new InvocationMetadata(requestId: 'audit', version: 'v1', operationId: 'audit.read', method: 'GET'));
    $invocation = new OperationInvocation(operation: $operation, context: $context, input: JsonDocuments::capture([]), parameters: [], idempotencyKey: null);
    new ReflectionMethod(BudgetAdmission::class, 'reserve')->invoke($admission, $invocation, new EstimatedCost(units: 1, recipeHash: hash('sha256', 'audit')));
}
$sql = new SqlOperationBudget(connection: new Connection(new PDO('sqlite::memory:')), clock: new FrozenClock(new DateTimeImmutable('2026-10-08T00:00:00Z')),
    scopeHmacSecret: new SecretRef('audit-only-placeholder'));
$keys = new ReflectionMethod(SqlOperationBudget::class, 'keys');
$first = $keys->invoke($sql, $capture->scopes[0]);
$second = $keys->invoke($sql, $capture->scopes[1]);
echo json_encode([
    'installed_manifest_shape' => $shapeResults,
    'principal_budget_collision' => [
        'principals' => ['user:5', 'agent:5'],
        'captured_actor_ids' => array_map(static fn (BudgetScope $scope): string => $scope->actorId, $capture->scopes),
        'actor_bucket_keys_equal' => $first['actor'] === $second['actor'],
        'uses_sqlite_memory_only' => true,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
