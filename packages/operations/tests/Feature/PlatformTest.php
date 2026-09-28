<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\ApiController;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\RequestContextProvider;
use CoreX\Api\OpenApiDocumentGenerator;
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\OperationErrorBoundary;
use CoreX\Api\OperationRegistry;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\Tests\Support\NoteHandler;
use CoreX\Api\Tests\TestCase;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

final class PlatformTest extends TestCase
{
    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modules = new ModuleFixture($this->compile([
            $this->declaration('notes.create', 'POST', '/notes', 201),
            $this->declaration('notes.card', 'GET', '/notes/{id}'),
            $this->declaration('notes.options', 'GET', '/notes/options'),
            Api::entity('notes.note'),
        ]));
        $this->tenants = new ContextFixture;
        $this->tenants->initialize($this->account('A'));
        $this->identity = new IdentityFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        NoteHandler::$calls = 0;
    }

    private function account(string $id): AccountRef
    {
        return new AccountRef($id, strtolower($id), 'active', [], []);
    }

    private function declaration(string $id, string $method, string $path, int $status = 200): Api
    {
        return Api::operation(
            id: $id, method: $method, path: $path, handler: NoteHandler::class, ability: 'notes.read',
            requestSchema: ['type' => 'object', 'additionalProperties' => false, 'required' => ['title'],
                'properties' => ['title' => ['type' => 'string', 'maxLength' => 100]]],
            responseSchema: ['type' => 'object'], rules: ['title' => ['required', 'string', 'max:100']],
            successStatus: $status,
        );
    }

    /** @param list<Api> $declarations */
    private function compile(array $declarations): CompiledRegistry
    {
        return app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends($declarations),
        ], installedPackages: []);
    }

    private function allow(): void
    {
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return $operation->ability === 'notes.read' && $context->principal()->id() === 'trusted-user';
            }
        });
    }

    private function dispatch(string $method = 'POST', string $path = 'notes', string $body = '{"title":"Hello"}', string $version = 'v1'): JsonResponse
    {
        $request = Request::create('/chosen/'.$version.'/'.$path, $method,
            $method === 'GET' ? ['title' => 'Hello'] : [], server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUEST_ID' => 'request-123'], content: $body);

        return $this->app->make(OperationDispatcher::class)->dispatch($request, $version, $path);
    }

    public function test_default_authentication_and_authorization_deny(): void
    {
        self::assertSame(401, $this->dispatch()->getStatusCode());
        $this->app->instance(PrincipalResolver::class, $this->identity);
        self::assertSame(403, $this->dispatch()->getStatusCode());
        self::assertSame(0, NoteHandler::$calls);
    }

    public function test_explicit_mutation_receives_trusted_context_and_envelope(): void
    {
        $this->allow();
        $response = $this->dispatch();
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('request-123', $response->headers->get('X-Request-ID'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(['request_id' => 'request-123', 'correlation_id' => 'request-123', 'api_version' => 'v1'], $response->getData(true)['meta']);
        self::assertSame('trusted-user', $response->getData(true)['data']['actor']);
        self::assertSame('A', $response->getData(true)['data']['account']);
        self::assertNull($response->getData(true)['error']);
    }

    public function test_unknown_routes_methods_versions_and_metadata_are_not_executable(): void
    {
        $this->allow();
        foreach ([['DELETE', 'notes', 'v1'], ['POST', 'undeclared', 'v1'], ['POST', 'notes', 'v2'], ['GET', 'notes.note', 'v1']] as [$method, $path, $version]) {
            self::assertSame(404, $this->dispatch($method, $path, version: $version)->getStatusCode());
        }
        self::assertSame(0, NoteHandler::$calls);
        self::assertSame('notes.options', $this->app->make(OperationRegistry::class)->match('v1', 'GET', '/notes/options')[0]->id);
    }

    public function test_missing_boxed_and_disabled_contexts_fail_closed(): void
    {
        $this->allow();
        $this->tenants->end();
        self::assertSame('context_required', $this->dispatch()->getData(true)['error']['code']);
        $this->tenants->value = new TenantContext($this->account('A'), boxed: true);
        self::assertSame(403, $this->dispatch()->getStatusCode());
        $this->tenants->initialize($this->account('A'));
        $this->modules->enabled = false;
        self::assertSame('module_unavailable', $this->dispatch()->getData(true)['error']['code']);
        self::assertSame(0, NoteHandler::$calls);
    }

    public function test_boxed_profile_requires_explicit_configuration(): void
    {
        $this->allow();
        $this->tenants->value = new TenantContext($this->account('A'), boxed: true);
        $this->app['config']->set('corex-api.require_physical_tenancy', false);
        self::assertSame(201, $this->dispatch()->getStatusCode());
    }

    public function test_context_and_module_state_are_read_on_every_dispatch(): void
    {
        $this->allow();
        $dispatcher = $this->app->make(OperationDispatcher::class);
        $request = Request::create('/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Hello"}');
        foreach (['A', 'B', 'A'] as $id) {
            $this->tenants->initialize($this->account($id));
            self::assertSame($id, $dispatcher->dispatch($request, 'v1', 'notes')->getData(true)['data']['account']);
        }
        $this->modules->enabled = false;
        self::assertSame(404, $dispatcher->dispatch($request, 'v1', 'notes')->getStatusCode());
        self::assertSame(['A', 'B', 'A', 'A'], $this->modules->visitedAccounts);
    }

    public static function malformedBodies(): array
    {
        return [['{'], ['null'], ['[]'], ['{}'], ['{"title":3}'], ['{"title":"Hello","actor":"forged"}'],
            ['{"title":"Hello","connection":"other"}'], ['{"title":"Hello","workspace":"foreign"}']];
    }

    #[DataProvider('malformedBodies')]
    public function test_malformed_or_forged_input_is_rejected(string $body): void
    {
        $this->allow();
        self::assertSame(422, $this->dispatch(body: $body)->getStatusCode());
        self::assertSame(0, NoteHandler::$calls);
    }

    public static function handlerErrors(): array
    {
        return [['hidden', 404], ['foreign', 404], ['conflict', 409], ['broken', 500], ['unauthorized', 403], ['unauthenticated', 401], ['invalid', 422]];
    }

    #[DataProvider('handlerErrors')]
    public function test_handler_errors_have_stable_status_and_no_infrastructure_details(string $id, int $status): void
    {
        $this->allow();
        $response = $this->dispatch('GET', 'notes/'.$id);
        self::assertSame($status, $response->getStatusCode());
        self::assertNull($response->getData(true)['data']);
        self::assertStringNotContainsString('secret', $response->getContent());
        self::assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_compilation_preserves_provenance_roundtrip_and_old_resource_metadata(): void
    {
        $roundtrip = CompiledRegistry::fromArray($this->modules->registry->toArray());
        self::assertSame($this->modules->registry->toArray(), $roundtrip->toArray());
        self::assertSame('example/notes', $roundtrip->api[0]->declaringModule);
        self::assertSame('entity', $roundtrip->api[3]->mode);
        $source = $this->declaration('notes.create', 'POST', '/notes');
        $source->declaringModule = 'forged/module';
        $compiled = $this->compile([$source]);
        self::assertSame('example/notes', $compiled->api[0]->declaringModule);
        self::assertSame('forged/module', $source->declaringModule);
    }

    public function test_duplicate_parameterized_routes_are_compile_errors(): void
    {
        $this->expectException(CompilationException::class);
        $this->compile([$this->declaration('first', 'GET', '/notes/{id}'), $this->declaration('second', 'GET', '/notes/{other}')]);
    }

    public function test_openapi_is_deterministic_and_derived_from_operations_only(): void
    {
        $schema = $this->app->make(OpenApiDocumentGenerator::class);
        $document = $schema->document('v1', 'https://example.test/chosen/v1');
        $paths = (array) $document['paths'];
        self::assertSame('3.1.0', $document['openapi']);
        self::assertCount(3, $paths);
        self::assertSame('notes.create', $paths['/notes']['post']['operationId']);
        self::assertSame('notes.read', $paths['/notes']['post']['x-corex-ability']);
        self::assertSame(['type' => 'object'], $paths['/notes']['post']['responses'][201]['content']['application/json']['schema']['properties']['data']);
        $error = $paths['/notes']['post']['responses'][403]['content'];
        self::assertSame(
            $error['application/json']['schema']['properties']['error']['properties']['code'],
            $error['application/problem+json']['schema']['properties']['code'],
        );
        self::assertSame(['request_id', 'correlation_id', 'api_version'], $error['application/json']['schema']['properties']['meta']['required']);
        self::assertSame(json_encode($document), json_encode($schema->document('v1', 'https://example.test/chosen/v1')));
        $this->modules->registry = $this->compile(array_reverse($this->modules->registry->api));
        self::assertSame(json_encode($document), json_encode($schema->document('v1', 'https://example.test/chosen/v1')));
        self::assertSame('{}', json_encode($schema->document('v9')['paths']));
    }

    public function test_provider_mounts_package_routes_and_application_can_mount_an_explicit_boundary(): void
    {
        self::assertTrue($this->app['router']->has('corex.api.discovery'));
        self::assertTrue($this->app['router']->has('corex.api.operation'));
        $this->allow();
        $this->app['router']->match(['GET', 'POST'], '/chosen/{workspace}/{version}/{path?}', ApiController::class)->where('path', '.*');
        $this->postJson('/chosen/alpha/v1/notes', ['title' => 'Hello'])->assertCreated()->assertJsonPath('data.actor', 'trusted-user');
        $this->getJson('/chosen/alpha/v1/undeclared')->assertNotFound();
    }

    public function test_error_boundary_normalizes_upstream_errors_with_named_version_and_request_id(): void
    {
        $this->app['router']->get('/upstream/{version}/{status}', static function (string $version, string $status): never {
            abort((int) $status, 'private infrastructure secret');
        })->middleware(OperationErrorBoundary::class);

        foreach ([401, 403, 404, 409, 422, 429, 400, 413, 415, 500, 503] as $status) {
            $response = $this->getJson('/upstream/v2/'.$status, ['X-Request-ID' => 'upstream-123']);
            $response->assertStatus($status >= 500 ? 500 : $status)
                ->assertJsonPath('meta.api_version', 'v2')
                ->assertJsonPath('meta.request_id', 'upstream-123')
                ->assertHeader('X-Request-ID', 'upstream-123');
            self::assertStringNotContainsString('secret', $response->getContent());
        }
    }

    public function test_generated_request_id_is_shared_by_the_boundary_and_dispatcher(): void
    {
        $this->allow();
        $this->app['router']->post('/bounded/{version}/{path}', ApiController::class)->middleware(OperationErrorBoundary::class);
        $response = $this->postJson('/bounded/v1/notes', ['title' => 'Hello'], ['X-Request-ID' => str_repeat('x', 129)]);
        $response->assertCreated();
        $id = $response->json('meta.request_id');
        self::assertMatchesRegularExpression('/^[a-f0-9-]{36}$/D', $id);
        $response->assertHeader('X-Request-ID', $id);
    }

    public function test_query_in_a_mutation_and_non_json_body_are_rejected(): void
    {
        $this->allow();
        $this->app['router']->post('/strict/{version}/{path}', ApiController::class);
        $this->postJson('/strict/v1/notes?actor=forged', ['title' => 'Hello'])->assertUnprocessable();
        $this->post('/strict/v1/notes', ['title' => 'Hello'])->assertUnprocessable();
        self::assertSame(0, NoteHandler::$calls);
    }

    public function test_disabled_account_is_rejected(): void
    {
        $this->allow();
        $this->tenants->initialize(new AccountRef('A', 'a', 'suspended', [], []));
        self::assertSame('account_unavailable', $this->dispatch()->getData(true)['error']['code']);
        self::assertSame(0, NoteHandler::$calls);
    }

    public static function invalidDeclarations(): array
    {
        return [['version', 'v0'], ['method', 'TRACE'], ['path', '/notes/{id}/{id}'],
            ['path', '/notes/{id?}'], ['ability', ''], ['successStatus', 204],
            ['inputRules', ['title' => [123]]], ['inputRules', ['title' => ['named' => 'required']]]];
    }

    #[DataProvider('invalidDeclarations')]
    public function test_invalid_declarations_fail_compilation(string $property, mixed $value): void
    {
        $declaration = $this->declaration('notes.create', 'POST', '/notes');
        $declaration->$property = $value;
        $this->expectException(CompilationException::class);
        $this->compile([$declaration]);
    }

    public function test_equal_specificity_overlapping_routes_are_compile_errors(): void
    {
        $this->expectException(CompilationException::class);
        $this->compile([$this->declaration('first', 'GET', '/notes/{id}/fields'), $this->declaration('second', 'GET', '/notes/options/{field}')]);
    }

    public function test_equivalent_cross_method_templates_require_identical_parameter_names(): void
    {
        $this->expectException(CompilationException::class);
        $this->compile([$this->declaration('first', 'GET', '/notes/{id}'), $this->declaration('second', 'POST', '/notes/{slug}')]);
    }

    public function test_module_adapters_receive_context_only_during_the_current_operation(): void
    {
        $this->allow();
        $this->app['router']->get('/adapters/{version}/{path}', ApiController::class)->where('path', '.*');
        $provider = $this->app->make(RequestContextProvider::class);
        $handler = new class($provider) implements OperationHandler
        {
            public function __construct(private readonly RequestContextProvider $provider) {}

            public function handle(RequestContext $context, array $input, array $parameters): array
            {
                PlatformTest::assertSame($context, $this->provider->current());

                return ['actor' => $this->provider->current()->principal()->id()];
            }
        };
        $this->app->instance(NoteHandler::class, $handler);
        $this->getJson('/adapters/v1/notes/1?title=Hello')->assertOk()->assertJsonPath('data.actor', 'trusted-user');
        $this->expectException(ApiError::class);
        $provider->current();
    }
}
