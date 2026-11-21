<?php

declare(strict_types=1);

namespace CoreX\Workflow\Tests;

use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CoreServiceProvider;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\DefinitionRequest;
use CoreX\Workflow\Data\EngineEffectRef;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\StartOutcome;
use CoreX\Workflow\WorkflowServiceProvider;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\PackmanServiceProvider;
use Packman\Schema\Laravel\SchemaServiceProvider;
use Packman\Schema\Testing\MigratesSchemaSets;
use RuntimeException;

final class TestTenantContextResolver implements TenantContextResolver
{
    public function current(): TenantContext
    {
        return new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'workflow-test', 'active', [], []));
    }
}

final class FakeEngineAdapter implements EngineAdapter
{
    /** @var list<TriggerRequest> */
    public array $starts = [];

    /** @var list<EngineEffectRef> */
    public array $lookups = [];

    public ?StartResult $startOutcome = null;

    public ?StartResult $lookupOutcome = null;

    public bool $statusLookup = true;

    public function provider(DefinitionRequest $request): ?EngineProvider
    {
        return new EngineProvider('fixture.workflow', statusLookup: $this->statusLookup);
    }

    public function reconcile(EngineEffectRef $effect): StartResult
    {
        $this->lookups[] = $effect;

        return $this->lookupOutcome ?? new StartResult(StartOutcome::Confirmed, 'instance:1');
    }

    public function start(TriggerRequest $request): StartResult
    {
        if (DB::connection()->transactionLevel() !== 0
            || ! DB::table('sys_workflow_intents')->where('effect_key', $request->effectKey)->where('state', 'sending')->exists()) {
            throw new RuntimeException('Engine start ran before its sending commit.');
        }
        $this->starts[] = $request;

        return $this->startOutcome ?? new StartResult(StartOutcome::Confirmed, 'instance:1');
    }

    public function signal(SignalRequest $request): SignalResult
    {
        return new SignalResult(status: 'buffered');
    }

    public function transition(TransitionRequest $request): TransitionResult
    {
        return new TransitionResult(status: 'completed');
    }
}

final class TestRecoveryAuthority implements ProviderRecoveryAuthority
{
    public bool $allowed = false;

    public function authorize(ProviderEffectRef $effect): void
    {
        if (! $this->allowed) {
            throw new ScopeContextMismatch('Workflow fixture has no recovery authority.');
        }
    }
}

abstract class TestCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackmanServiceProvider::class, SchemaServiceProvider::class, CoreServiceProvider::class, WorkflowServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        $database = getenv('COREX_WORKFLOW_PG_DATABASE');

        if ($database !== false && str_ends_with($database, '_test')) {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
                'port' => getenv('PGSQL_PORT') ?: '5434',
                'database' => $database,
                'username' => getenv('PGSQL_USERNAME') ?: 'corex',
                'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
                'charset' => 'utf8',
                'timezone' => 'UTC',
                'prefix' => '',
            ]);
        }
        $app['config']->set('corex-workflow.enforce_declarations', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-workflow:tenant'], DB::getDefaultConnection());
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TenantContextResolver::class, new TestTenantContextResolver);
        $this->app->instance(EngineAdapter::class, new FakeEngineAdapter);
        $recovery = new TestRecoveryAuthority;
        $this->app->instance(TestRecoveryAuthority::class, $recovery);
        $this->app->instance(ProviderRecoveryAuthority::class, $recovery);
        WorkScopeFixture::install($this->app)->scope();
        DB::table('sys_workflow_intents')->delete();
    }
}
