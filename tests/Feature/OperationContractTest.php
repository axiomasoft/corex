<?php

declare(strict_types=1);

namespace CoreX\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Internal\ResolvedContext;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
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
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Internal\ToolDescriptorFactory;
use CoreX\Tools\ToolServiceProvider;
use Illuminate\Support\Facades\Schema;

final class OperationContractTest extends TestCase
{
    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('app.key', 'p13-contract-test-secret');
        Schema::dropIfExists('sys_api_idempotency');
        app(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-operations:tenant'], app(ApiSchemaRegistrar::class)->tenant()->getName());

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
        $query = Api::operation('notes.read', 'GET', '/notes/{id}', P13QueryHandler::class, 'notes.read', self::schema(), $output)
            ->asQuery()->fields(read: ['/id', '/title', '/actor', '/account'], write: []);
        $async = Api::operation('notes.export', 'POST', '/notes/export', P13AsyncHandler::class, 'notes.write', $asyncInput, $asyncOutput)
            ->asAsyncCommand()->idempotency('required')->effect('tenant_transactional');
        $tool = Api::exposeTool('notes.create', 'v1', 'notes.create', 'v1', 'write', 'domain-key', $input, $output, ['title' => 'title']);
        $asyncTool = Api::exposeTool('notes.export', 'v1', 'notes.export', 'v1', 'write', 'domain-key', $asyncInput, $asyncOutput, ['format' => 'format']);
        $this->modules = new ModuleFixture(app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$query, $command, $async, $tool, $asyncTool]),
        ], installedPackages: []));
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->identity = new IdentityFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->identity);
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
        $operationRoutes = 0;

        foreach ($this->app['router']->getRoutes() as $route) {
            if ($route->getName() === 'corex.api.operation') {
                $operationRoutes++;
            }
        }

        self::assertSame(1, $operationRoutes);
        $query = $this->getJson('/api/v1/notes/n-1');
        self::assertSame(200, $query->getStatusCode(), (string) $query->getContent());
        $query->assertJsonPath('data.id', 'n-1')->assertJsonPath('data.account', 'account-a');

        $command = $this->call('POST', '/api/v1/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":"Created","account":"forged"}');
        $command->assertStatus(422);
        self::assertSame(0, P13CommandHandler::$calls);

        $command = $this->call('POST', '/api/v1/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":"Created"}');
        $command->assertOk()->assertJsonPath('data.account', 'account-a');
        $replay = $this->call('POST', '/api/v1/notes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-command-1',
        ], content: '{"title":"Created"}');
        $replay->assertOk();
        self::assertSame(1, P13CommandHandler::$calls);

        $async = $this->call('POST', '/api/v1/notes/export', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'p13-export-1',
        ], content: '{}');
        $async->assertStatus(202)->assertHeader('Location', '/api/v1/notes/export/status');

        $asyncDeclaration = $this->toolDeclaration('notes.export');
        $asyncTool = $this->app->make(ToolInvoker::class)->invoke(
            new ResolvedContext($this->tenants->current(), $this->identity, new InvocationMetadata('p13-export-tool', 'v1', 'notes.export', 'POST')),
            new ToolCall('notes.export', 'v1', ToolDescriptorFactory::fromDeclaration($asyncDeclaration)->contractHash, new JsonObject('{}'), 'p13-export-tool', operationKey: 'p13-export-1'),
        );
        self::assertSame(ToolOutcome::Committed, $asyncTool->outcome, (string) $asyncTool->errorCode);
        self::assertTrue($asyncTool->replayed);
        self::assertSame($async->json('data'), json_decode((string) $asyncTool->structuredData?->canonicalJson, true));

        $declaration = $this->toolDeclaration('notes.create');
        $descriptor = ToolDescriptorFactory::fromDeclaration($declaration);
        $context = new ResolvedContext($this->tenants->current(), $this->identity, new InvocationMetadata('p13-tool', 'v1', 'notes.create', 'POST'));
        $tool = $this->app->make(ToolInvoker::class)->invoke($context, new ToolCall(
            'notes.create', 'v1', $descriptor->contractHash,
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
        $fixture = dirname(__DIR__, 2).'/tests/fixtures/public-error-contract.php';

        try {
            $this->artisan('corex:operations:snapshot', ['directory' => $directory, '--fixture' => $fixture])->assertSuccessful();
            $this->artisan('corex:operations:snapshot', ['directory' => $directory, '--fixture' => $fixture, '--check' => true])->assertSuccessful();
            $path = $directory.'/operations.json';
            $stale = (string) file_get_contents($path).' ';
            file_put_contents($path, $stale);
            $this->artisan('corex:operations:snapshot', ['directory' => $directory, '--fixture' => $fixture, '--check' => true])->assertExitCode(1);
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
        $operation = null;

        foreach ($this->modules->registry->api as $candidate) {
            if ($candidate->mode === 'operation' && $candidate->operationId === 'notes.create') {
                $operation = $candidate;

                break;
            }
        }
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

        return ['id' => $parameters['id'], 'title' => 'Found', 'actor' => $context->principal()->id(), 'account' => $context->tenant()->account->id];
    }
}

final class P13CommandHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return ['id' => 'n-2', 'title' => $input['title'], 'actor' => $context->principal()->id(), 'account' => $context->tenant()->account->id];
    }
}

final class P13AsyncHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return ['operation_id' => 'job-1', 'status_url' => '/api/v1/notes/export/status'];
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
        return Api::operation('notes.create', 'POST', '/notes', P13CommandHandler::class, 'notes.write', $input, $output, ['title' => ['required', 'string']])
            ->asCommand()->idempotency('required')->effect('tenant_transactional')
            ->fields(read: ['/id', '/title', '/actor', '/account'], write: ['/title']);
    }

    public static function signature(ApiProfile $profile, CursorPayload $cursor): string
    {
        return $profile->fingerprint().'|'.$cursor->ordering;
    }
}
