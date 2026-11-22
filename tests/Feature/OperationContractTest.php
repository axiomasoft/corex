<?php

declare(strict_types=1);

namespace CoreX\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Contracts\OperationAcceptance;
use CoreX\Operations\Invocation\Data\AcceptedOperation;
use CoreX\Operations\Invocation\Internal\AuthenticatedRequestContext;
use CoreX\Operations\Invocation\Internal\ResolvedContext;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Pagination\Data\CursorPayload;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Versioning\Data\ApiProfile;
use CoreX\Operations\Versioning\Internal\ApiProfileFactory;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Internal\ToolDescriptorFactory;
use CoreX\Tools\ToolsServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Symfony\Component\HttpFoundation\Response;

final class OperationContractTest extends TestCase
{
    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ToolsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-operations.routes.middleware', [P13VerifiedClient::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
        Schema::dropIfExists('sys_api_idempotency');
        resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());

        $input = self::schema(['title' => ['type' => 'string']]);
        $output = self::schema([
            'id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'actor' => ['type' => 'string'],
            'account' => ['type' => 'string'],
        ]);
        $asyncOutput = self::schema([
            'operation_id' => ['type' => 'string'],
            'status_url' => ['type' => 'string'],
        ]);
        $asyncInput = self::schema(['format' => ['type' => 'string']]);

        // The P14 consumer declaration uses only the frozen module extender and handler contract.
        $command = P14FrozenConsumer::declaration($input, $output);
        $query = Api::operation('notes.read', 'GET', '/notes/{id}', P13QueryHandler::class, 'notes.read', self::schema(), $output, version: 'v2')
            ->asQuery()->fields(read: ['/id', '/title', '/actor', '/account'], write: []);
        $async = Api::operation('notes.export', 'POST', '/notes/export', P13AsyncHandler::class, 'notes.write', $asyncInput, $asyncOutput, version: 'v2', successStatus: 202)
            ->asAsyncCommand()->idempotency('required')->effect('tenant_transactional');
        $tool = Api::exposeTool('notes.create', 'v2', 'notes.create', 'v2', 'write', 'domain-key', $input, $output, ['title' => 'title']);
        $asyncTool = Api::exposeTool('notes.export', 'v2', 'notes.export', 'v2', 'write', 'domain-key', $asyncInput, $asyncOutput, ['format' => 'format']);
        $this->modules = new ModuleFixture(resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$query, $command, $async, $tool, $asyncTool]),
        ], installedPackages: []));
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->identity = new IdentityFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $scope = WorkScopeFixture::install(
            app: $this->app,
            principalId: $this->identity->principalId(),
            principalKind: $this->identity->principalType()->value,
        );
        $scope->register(context: $this->tenants->current(), connection: resolve(ApiSchemaConnections::class)->tenant());
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return true;
            }
        });
        $this->app->instance(ToolPolicy::class, new class implements ToolPolicy
        {
            public function discover(ToolDescriptor $tool, RequestContext $context): bool
            {
                return true;
            }

            public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool
            {
                return true;
            }
        });
        $this->app->instance(ToolExecutionGuard::class, new class implements ToolExecutionGuard
        {
            public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
            {
                return $effect();
            }
        });
        P13QueryHandler::$calls = P13CommandHandler::$calls = P13AsyncHandler::$calls = 0;
    }

    public function test_real_routes_and_exposed_tool_share_compiled_contract_and_guards(): void
    {
        // Install the same declaring module that owns the HTTP and tool operation.
        $files = new Filesystem;
        $base = $this->app->basePath();
        $fixture = sys_get_temp_dir().'/corex-contract-notes-'.bin2hex(random_bytes(12));
        $files->makeDirectory($fixture.'/vendor/composer', 0700, true);
        $this->beforeApplicationDestroyed(function () use ($base, $files, $fixture): void {
            $this->app->setBasePath($base);
            $files->deleteDirectory($fixture);
        });
        $catalogue = json_decode($files->get(dirname(__DIR__, 2).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
        [$declaringModule] = $this->modules->compiled()->modules;
        $catalogue['packages'][] = ['name' => $declaringModule['name'], 'version' => $declaringModule['version'], 'type' => 'packman-module'];
        $files->put($fixture.'/vendor/composer/installed.json', json_encode($catalogue, JSON_THROW_ON_ERROR));
        $this->app->setBasePath($fixture);
        $operationRoutes = 0;

        foreach ($this->app['router']->getRoutes() as $route) {
            if ($route->getName() === 'corex.api.operation') {
                $operationRoutes++;
            }
        }

        self::assertSame(1, $operationRoutes);
        $this->getJson('/api/v1/notes/n-1')->assertNotFound();
        $this->getJson('/api/v3/notes/n-1')->assertNotFound();
        $query = $this->getJson('/api/v2/notes/n-1');
        self::assertSame(200, $query->getStatusCode(), (string) $query->getContent());
        $query->assertJsonPath('data.id', 'n-1')->assertJsonPath('data.account', 'account-a');

        $command = $this->call('POST', '/api/v2/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":"Created","account":"forged"}');
        $command->assertForbidden()->assertJsonPath('code', 'operations.access_denied')->assertJsonPath('path', '/account');
        $this->call('POST', '/api/v2/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":42}')->assertUnprocessable()->assertJsonPath('code', 'operations.validation_failed');
        self::assertSame(0, P13CommandHandler::$calls);

        $command = $this->call('POST', '/api/v2/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":"Created"}');
        $command->assertOk()->assertJsonPath('data.account', 'account-a');
        $replay = $this->call('POST', '/api/v2/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":"Created"}');
        $replay->assertOk();
        self::assertSame(1, P13CommandHandler::$calls);

        $async = $this->call('POST', '/api/v2/notes/export', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-export-1',
        ], content: '{}');
        $async->assertStatus(202)->assertHeader('Location', '/api/v2/notes/export/status');

        $asyncDeclaration = $this->toolDeclaration('notes.export');
        $asyncTool = $this->app->make(ToolInvoker::class)->invoke(
            $this->toolContext('p13-export-tool', 'notes.export'),
            new ToolCall('notes.export', 'v2', ToolDescriptorFactory::fromDeclaration($asyncDeclaration)->contractHash, new JsonObject('{}'), 'p13-export-tool', operationKey: 'p13-export-1'),
        );
        self::assertSame(ToolOutcome::Committed, $asyncTool->outcome, (string) $asyncTool->errorCode);
        self::assertTrue($asyncTool->replayed);
        self::assertSame($async->json('data'), json_decode((string) $asyncTool->structuredData?->canonicalJson, true));

        $declaration = $this->toolDeclaration('notes.create');
        $descriptor = ToolDescriptorFactory::fromDeclaration($declaration);
        $context = $this->toolContext('p13-tool', 'notes.create');
        $tool = $this->app->make(ToolInvoker::class)->invoke($context, new ToolCall(
            'notes.create', 'v2', $descriptor->contractHash,
            new JsonObject(JsonCanonical::encode(['title' => 'Tool'])), 'p13-tool', operationKey: 'p13-tool-1',
        ));
        self::assertSame(ToolOutcome::Committed, $tool->outcome, (string) $tool->errorCode);
        self::assertSame('account-a', json_decode((string) $tool->structuredData?->canonicalJson, true)['account']);
        self::assertSame(1, P13QueryHandler::$calls);
        self::assertSame(2, P13CommandHandler::$calls);
        self::assertSame(1, P13AsyncHandler::$calls);
        self::assertContains('account-a', $this->modules->visitedAccounts);
        self::assertNotContains('forged', $this->modules->visitedAccounts);
    }

    public function test_snapshot_check_rejects_stale_bytes_without_rewriting_them(): void
    {
        $directory = sys_get_temp_dir().'/corex-p13-freeze-'.bin2hex(random_bytes(6));

        try {
            foreach (['v1', 'v3'] as $unsupported) {
                $this->artisan('corex:operations:snapshot', ['directory' => $directory, '--major' => $unsupported])->assertExitCode(2);
                self::assertDirectoryDoesNotExist($directory);
            }
            $this->artisan('corex:operations:snapshot', ['directory' => $directory])->assertSuccessful();
            $this->artisan('corex:operations:snapshot', ['directory' => $directory, '--major' => 'v2', '--check' => true])->assertSuccessful();
            $path = $directory.'/operations.json';
            $stale = (string) file_get_contents($path).' ';
            file_put_contents($path, $stale);
            $this->artisan('corex:operations:snapshot', ['directory' => $directory, '--check' => true])->assertExitCode(1);
            self::assertSame($stale, file_get_contents($path));
        } finally {
            foreach (glob($directory.'/*') ?: [] as $path) {
                unlink($path);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function test_p14_consumer_compiles_against_frozen_public_surface(): void
    {
        $operation = array_find($this->modules->registry->api, fn ($candidate) => $candidate->mode === 'operation' && $candidate->operationId === 'notes.create');
        self::assertInstanceOf(Api::class, $operation);
        self::assertSame('command', $operation->operationMode);
        self::assertSame('required', $operation->idempotencyRequirement);
        self::assertSame(['/title'], $operation->writeFields);

        $profile = $this->app->make(ApiProfileFactory::class)->make($this->modules->compiled(), $this->app->make(ApiOptions::class));
        $cursor = new CursorPayload(1, 'notes.by_id', 'asc', ['n-1'], 'scope', 1, 2, 'k1');
        self::assertSame($profile->fingerprint().'|notes.by_id', P14FrozenConsumer::signature($profile, $cursor));
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function schema(array $properties = []): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties];
    }

    private function toolContext(string $requestId, string $operation): RequestContext
    {
        return new AuthenticatedRequestContext(
            new ResolvedContext($this->tenants->current(), $this->identity, new InvocationMetadata($requestId, 'v2', $operation, 'POST')),
            P13VerifiedClient::ID,
        );
    }

    private function toolDeclaration(string $code): Api
    {
        foreach ($this->modules->registry->api as $candidate) {
            if ($candidate->toolCode === $code) {
                return $candidate;
            }
        }

        self::fail('Missing compiled tool '.$code);
    }
}

final class P13QueryHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return ['id' => $parameters['id'], 'title' => 'Found', 'actor' => $context->principal()->principalId(), 'account' => $context->tenant()->account->id];
    }
}

final class P13CommandHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return ['id' => 'n-2', 'title' => $input['title'], 'actor' => $context->principal()->principalId(), 'account' => $context->tenant()->account->id];
    }
}

final class P13AsyncHandler implements OperationAcceptance, OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return ['operation_id' => 'job-1', 'status_url' => '/api/v2/notes/export/status'];
    }

    public function acceptance(RequestContext $context, array $result): AcceptedOperation
    {
        return new AcceptedOperation('job-1', '/api/v2/notes/export/status');
    }
}

final class P13VerifiedClient
{
    public const string ID = 'test-p13-client';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('corex.client_id', self::ID);

        return $next($request);
    }
}

/** A P14-shaped consumer: this class names only exported modules and API symbols. */
final class P14FrozenConsumer
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public static function declaration(array $input, array $output): Api
    {
        return Api::operation('notes.create', 'POST', '/notes', P13CommandHandler::class, 'notes.write', $input, $output, ['title' => ['required', 'string']], version: 'v2')
            ->asCommand()->idempotency('required')->effect('tenant_transactional')
            ->fields(read: ['/id', '/title', '/actor', '/account'], write: ['/title']);
    }

    public static function signature(ApiProfile $profile, CursorPayload $cursor): string
    {
        return $profile->fingerprint().'|'.$cursor->ordering;
    }
}
