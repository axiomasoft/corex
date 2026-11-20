<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Versioning\Internal\ApiProfileFactory;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final class ApiRoutesTest extends TestCase
{
    private static bool $routesEnabled = true;

    private ModuleFixture $modules;

    private IdentityFixture $identity;

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-operations.routes.enabled', self::$routesEnabled);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('app.key', 'api-routes-idempotency-secret');
        Schema::dropIfExists('sys_api_idempotency');

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $schema = ['type' => 'object'];
        $sync = Api::operation('notes.create', 'POST', '/notes', RouteNoteHandler::class, 'notes.write', $schema, $schema)
            ->asCommand()->idempotency('required')->effect('tenant_transactional')->capability('notes.write.v1');
        $async = Api::operation('notes.export', 'POST', '/notes/export', RouteAsyncHandler::class, 'notes.write', $schema, $schema, successStatus: 202)
            ->asAsyncCommand()->idempotency('required')->effect('tenant_transactional');
        $old = Api::operation('notes.legacy', 'GET', '/notes/legacy', RouteNoteHandler::class, 'notes.read', $schema, $schema)
            ->deprecated('notes.legacy', '2026-09-01', '2026-12-01', 'notes.create', 'https://example.test/migrate');
        $registry = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$sync, $async, $old]),
        ], installedPackages: []);
        $this->modules = new ModuleFixture($registry);
        $this->identity = new IdentityFixture;
        $tenants = new ContextFixture;
        $tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $tenants);
        $this->app->instance(TenancyManager::class, $tenants);
        $this->app->instance(PrincipalResolver::class, $this->identity);
        WorkScopeFixture::install($this->app, $this->identity->principalId())->scope();
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public bool $allow = true;

            public function allows(Operation $operation, RequestContext $context): bool
            {
                return true;
            }
        });
    }

    public function test_route_list_is_exact_and_boot_is_idempotent(): void
    {
        $names = ['corex.api.discovery', 'corex.api.capabilities', 'corex.api.openapi', 'corex.api.operation'];

        foreach ($names as $name) {
            $this->assertTrue($this->app['router']->has($name));
        }

        $this->assertSame(1, collect($this->app['router']->getRoutes())->filter(
            static fn ($route): bool => $route->getName() === 'corex.api.operation',
        )->count());
        $this->app->register(OperationsServiceProvider::class, force: true);
        $this->assertSame(1, collect(Route::getRoutes())->filter(
            static fn ($route): bool => $route->getName() === 'corex.api.discovery',
        )->count());
    }

    public function test_root_discovery_exposes_code_profile_only(): void
    {
        $response = $this->getJson('/api');

        $response->assertOk()
            ->assertJsonPath('product', 'corex')
            ->assertJsonPath('default_major', 'v1');
        $this->assertStringNotContainsString('example/notes', $response->getContent());
        $this->assertStringNotContainsString('driver', $response->getContent());
        $this->assertStringNotContainsString('RouteNoteHandler', $response->getContent());
    }

    public function test_capabilities_follow_activation_and_ignore_forged_payload(): void
    {
        $before = $this->app->make(ApiProfileFactory::class)->make(
            $this->modules->compiled(),
            $this->app->make(ApiOptions::class),
        )->fingerprint();
        $enabled = $this->getJson('/api/v1/capabilities?account=forged&workspace=forged');
        $enabled->assertOk();
        $this->assertTrue($enabled->json('capabilities.0.enabled'));
        $this->modules->enabled = false;
        $disabled = $this->getJson('/api/v1/capabilities', ['account' => 'forged', 'workspace' => 'forged']);
        $disabled->assertOk();
        $this->assertFalse($disabled->json('capabilities.0.enabled'));
        $this->assertSame('capability_disabled', $disabled->json('capabilities.0.reason'));
        $after = $this->app->make(ApiProfileFactory::class)->make(
            $this->modules->compiled(),
            $this->app->make(ApiOptions::class),
        )->fingerprint();
        $this->assertSame($before, $after);
        $this->assertSame($before, $enabled->json('profile'));
    }

    public function test_async_sets_location_and_deprecation_headers_are_exact(): void
    {
        $async = $this->call('POST', '/api/v1/notes/export', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'export-1'], content: '{}');
        $async->assertStatus(202)->assertHeader('Location', '/api/v1/notes/export/status');
        $legacy = $this->getJson('/api/v1/notes/legacy');
        $legacy->assertOk()
            ->assertHeader('Deprecation', 'Tue, 01 Sep 2026 00:00:00 GMT')
            ->assertHeader('Sunset', 'Tue, 01 Dec 2026 00:00:00 GMT')
            ->assertHeader('Link', '<https://example.test/migrate>; rel="deprecation"')
            ->assertHeader('successor-version', 'notes.create');
        $sync = $this->call('POST', '/api/v1/notes', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'create-1'], content: '{}');
        $sync->assertOk();
        $this->assertFalse($sync->headers->has('Location'));
        $this->assertFalse($sync->headers->has('Deprecation'));
    }

    public function test_disabled_routes_register_none(): void
    {
        self::$routesEnabled = false;
        $this->refreshApplication();

        $this->assertFalse($this->app['router']->has('corex.api.discovery'));
        $this->assertFalse($this->app['router']->has('corex.api.operation'));

        self::$routesEnabled = true;
    }
}

final class RouteNoteHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['id' => 'new', 'account' => $context->tenant()->account->id];
    }
}

final class RouteAsyncHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['operation_id' => 'job-1', 'status_url' => '/api/v1/notes/export/status'];
    }
}
