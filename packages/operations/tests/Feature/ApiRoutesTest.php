<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationAcceptance;
use CoreX\Operations\Invocation\Data\AcceptedOperation;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Metadata\Contracts\EntityMetadata;
use CoreX\Operations\Metadata\Data\MetadataView;
use CoreX\Operations\Metadata\Laravel\MetadataOperationDeclarations;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Versioning\Internal\ApiProfileFactory;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
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
        $sync = Api::operation('notes.create', 'POST', '/notes', RouteNoteHandler::class, 'notes.write', $schema, $schema, version: 'v2')
            ->asCommand()->idempotency('required')->effect('tenant_transactional')->capability('notes.write.v1');
        $optional = Api::operation('notes.preview', 'POST', '/notes/preview', RouteNoteHandler::class, 'notes.write', $schema, $schema, version: 'v2')
            ->asCommand()->idempotency('optional')->effect('tenant_transactional');
        $async = Api::operation('notes.export', 'POST', '/notes/export', RouteAsyncHandler::class, 'notes.write', $schema, $schema, successStatus: 202, version: 'v2')
            ->asAsyncCommand()->idempotency('required')->effect('tenant_transactional');
        $old = Api::operation('notes.legacy', 'GET', '/notes/legacy', RouteNoteHandler::class, 'notes.read', $schema, $schema, version: 'v2')
            ->deprecated('notes.legacy', '2026-09-01', '2026-12-01', 'notes.create', 'https://example.test/migrate');
        $status = Api::operation('notes.export-status', 'GET', '/notes/export/status', RouteNoteHandler::class, 'notes.read', $schema, $schema, version: 'v2');
        $registry = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$sync, $optional, $async, $status, $old, ...MetadataOperationDeclarations::all()]),
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
        $references = Mockery::mock(ReferenceAdmission::class);
        $references->shouldReceive('enlist')->withArgs(static fn (
            DataScope $scope,
            ConnectionInterface $connection,
            ModuleRef $owner,
            ModuleRef $target,
        ): bool => $scope->accountId === 'A' && $connection->transactionLevel() > 0
            && $owner->package === 'axiomasoft/corex-operations' && $target->package === 'example/notes');
        $this->app->instance(ReferenceAdmission::class, $references);
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
            ->assertJsonPath('default_major', 'v2');
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
        $enabled = $this->getJson('/api/v2/capabilities?account=forged&workspace=forged');
        $enabled->assertOk();
        $this->assertTrue($enabled->json('capabilities.0.enabled'));
        $this->modules->enabled = false;
        $disabled = $this->getJson('/api/v2/capabilities', ['account' => 'forged', 'workspace' => 'forged']);
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
        $async = $this->call('POST', '/api/v2/notes/export', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'export-1'], content: '{}');
        $async->assertStatus(202)->assertHeader('Location', '/api/v2/notes/export/status')
            ->assertJsonPath('data.operation_id', 'job-1')->assertJsonPath('data.status_url', '/api/v2/notes/export/status');
        $this->getJson((string) $async->headers->get('Location'))->assertOk();
        $this->postJson('/api/v1/notes/export')->assertNotFound();
        $this->postJson('/api/v3/notes/export')->assertNotFound();
        $legacy = $this->getJson('/api/v2/notes/legacy');
        $legacy->assertOk()
            ->assertHeader('Deprecation', 'Tue, 01 Sep 2026 00:00:00 GMT')
            ->assertHeader('Sunset', 'Tue, 01 Dec 2026 00:00:00 GMT')
            ->assertHeader('Link', '<https://example.test/migrate>; rel="deprecation"')
            ->assertHeader('successor-version', 'notes.create');
        $sync = $this->call('POST', '/api/v2/notes', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'create-1'], content: '{}');
        $sync->assertOk();
        $this->assertFalse($sync->headers->has('Location'));
        $this->assertFalse($sync->headers->has('Deprecation'));
    }

    public function test_metadata_reauthorizes_before_conditional_release_and_openapi_stays_static(): void
    {
        $url = '/api/v2/entities/notes.note/fields';
        $static = $this->getJson('/api/v2/openapi.json')->assertOk()->getContent();
        $this->getJson($url)->assertStatus(503)->assertJsonPath('code', 'operations.capability_unavailable');
        $owner = new RouteFieldMetadata;
        $this->app->instance('fields.describe', $owner);

        $first = $this->getJson($url)->assertOk()->assertJsonPath('data.fields.0.id', 'title');
        $tag = $first->headers->get('ETag');
        self::assertIsString($tag);
        self::assertStringContainsString('private', (string) $first->headers->get('Cache-Control'));

        $cached = $this->getJson($url, ['If-None-Match' => $tag])->assertStatus(304)->assertHeader('ETag', $tag);
        self::assertSame('', $cached->getContent());
        $owner->allowed = false;
        $denied = $this->getJson($url, ['If-None-Match' => $tag])->assertForbidden();
        $denied->assertHeaderMissing('ETag')->assertJsonMissingPath('data.fields');
        $owner->allowed = true;
        $owner->revision = 'revision-2';
        $updated = $this->getJson($url, ['If-None-Match' => $tag])->assertOk()->assertJsonPath('data.schema_revision', 'revision-2');
        self::assertNotSame($tag, $updated->headers->get('ETag'));
        self::assertSame($static, $this->getJson('/api/v2/openapi.json')->assertOk()->getContent());
    }

    public function test_openapi_idempotency_header_matches_runtime_admission(): void
    {
        $post = fn (string $path, array $key = []) => $this->call('POST', '/api/v2'.$path, server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', ...$key,
        ], content: '{}');

        $post('/notes')->assertUnprocessable()->assertJsonPath('code', OperationsErrorCode::IdempotencyKeyRequired->value);
        $post('/notes/preview')->assertOk();
        $post('/notes/preview', ['HTTP_IDEMPOTENCY_KEY' => 'opaque / ? value'])->assertOk();
        $post('/notes', ['HTTP_IDEMPOTENCY_KEY' => 'x'])->assertOk();
        $post('/notes', ['HTTP_IDEMPOTENCY_KEY' => str_repeat('é', 127).'x'])->assertOk();
        $post('/notes', ['HTTP_IDEMPOTENCY_KEY' => str_repeat('é', 128)])
            ->assertUnprocessable()->assertJsonPath('code', OperationsErrorCode::IdempotencyKeyInvalid->value);
        $post('/notes', ['HTTP_IDEMPOTENCY_KEY' => ''])
            ->assertUnprocessable()->assertJsonPath('code', OperationsErrorCode::IdempotencyKeyInvalid->value);
        $post('/notes/preview', ['HTTP_IDEMPOTENCY_KEY' => ['a', 'b']])
            ->assertUnprocessable()->assertJsonPath('code', OperationsErrorCode::IdempotencyKeyInvalid->value);
        $this->getJson('/api/v2/notes/export/status', ['Idempotency-Key' => 'forbidden'])
            ->assertUnprocessable()->assertJsonPath('code', OperationsErrorCode::IdempotencyKeyForbidden->value);

        $paths = $this->getJson('/api/v2/openapi.json')->assertOk()->json('paths');
        foreach (['/notes' => true, '/notes/preview' => false, '/notes/export' => true] as $path => $required) {
            $parameters = array_column($paths[$path]['post']['parameters'], null, 'name');
            self::assertArrayHasKey('Idempotency-Key', $parameters);
            self::assertSame('header', $parameters['Idempotency-Key']['in']);
            self::assertSame($required, $parameters['Idempotency-Key']['required']);
            self::assertSame(['type' => 'string', 'minLength' => 1, 'maxLength' => 255], $parameters['Idempotency-Key']['schema']);
            self::assertStringContainsString('1 to 255 bytes', $parameters['Idempotency-Key']['description']);
        }
        self::assertNotContains('Idempotency-Key', array_column($paths['/notes/export/status']['get']['parameters'], 'name'));
        self::assertSame([
            'required' => true, 'schema' => ['type' => 'string', 'format' => 'uri-reference'],
        ], $paths['/notes/export']['post']['responses'][202]['headers']['Location']);
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

final class RouteAsyncHandler implements OperationAcceptance, OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['operation_id' => 'job-1'];
    }

    public function acceptance(RequestContext $context, array $result): ?AcceptedOperation
    {
        return new AcceptedOperation(operationRef: $result['operation_id'], statusUri: '/api/v2/notes/export/status');
    }
}

final class RouteFieldMetadata implements EntityMetadata
{
    public bool $allowed = true;

    public string $revision = 'revision-1';

    public function describe(DataScope $scope, EntityHandle $entity): MetadataView
    {
        if (! $this->allowed) {
            throw new ApiError(OperationsErrorCode::AccessDenied, 403, 'Metadata is unavailable.');
        }

        return new MetadataView(body: JsonDocuments::capture([
            'version' => 'v2', 'schema_revision' => $this->revision, 'entity' => $entity->value,
            'capabilities' => ['describe' => true], 'fields' => [['id' => 'title', 'type' => 'text', 'uses' => ['read']]],
        ]), witness: hash('sha256', $scope->principalId.'-title-'.$this->revision));
    }
}
