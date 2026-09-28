<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Tests\Support\CatalogueModuleFixture;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\MutatingHandler;
use CoreX\Api\Tests\Support\ToolGuardFixture;
use CoreX\Api\Tests\Support\ToolPolicyFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Api\ToolServiceProvider;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Tenancy\AccountRef;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ToolGuardConcurrencyTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        MutatingHandler::$calls = 0;

        Schema::dropIfExists('tool_effects');
        Schema::dropIfExists('tool_idempotency');

        Schema::create('tool_effects', static function (Blueprint $table): void {
            $table->id();
            $table->string('account_id');
            $table->string('operation_key');
            $table->text('payload');
            $table->timestamp('created_at');
        });

        Schema::create('tool_idempotency', static function (Blueprint $table): void {
            $table->string('operation_key')->primary();
            $table->string('binding_digest');
            $table->text('result_json');
            $table->string('account_id');
            $table->timestamp('created_at');
        });
    }

    public function test_equal_key_requests_replay_under_row_lock_without_duplicate_effects(): void
    {
        $registry = app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::tool(
                    code: 'notes.create',
                    handler: MutatingHandler::class,
                    ability: 'notes.create',
                    inputSchema: [
                        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'operation_key' => ['type' => 'string'],
                        ],
                    ],
                    outputSchema: [
                        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'status' => ['type' => 'string'],
                            'account' => ['type' => 'string'],
                        ],
                    ],
                    sideEffect: 'write',
                    idempotency: 'domain-key',
                )->titled('Create', 'Create'),
            ]),
        ], installedPackages: []);

        $descriptor = ToolDescriptor::fromDeclaration($registry->api[0]);
        $call = new ToolCall(
            code: 'notes.create',
            toolVersion: 'v1',
            expectedContractHash: $descriptor->contractHash,
            arguments: JsonObject::fromArray(['name' => 'alpha', 'operation_key' => 'concurrent-op']),
            requestId: 'req-concurrent',
            operationKey: 'concurrent-op',
        );

        $modules = new CatalogueModuleFixture($registry);
        $tenants = new ContextFixture;
        $tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $policy = new ToolPolicyFixture;
        $policy->allowInvoke = true;
        $guard = new ToolGuardFixture;
        $context = new ResolvedContext(
            tenantContext: $tenants->current(),
            authenticatedPrincipal: new IdentityFixture,
            requestMetadata: new InvocationMetadata('request-1', 'v1', 'tools.invoke', 'POST'),
        );
        $guard->approvedProofDigest = $guard->bindingDigest($descriptor, $context, $call);

        $this->app->instance(ModuleRegistry::class, $modules);
        $this->app->instance(ToolPolicy::class, $policy);
        $this->app->instance(ToolExecutionGuard::class, $guard);

        $invoker = $this->app->make(ToolInvoker::class);
        $first = $invoker->invoke($context, $call);
        $second = $invoker->invoke($context, $call);

        self::assertSame(ToolResult::OUTCOME_COMMITTED, $first->outcome);
        self::assertTrue($second->replayed);
        self::assertSame(1, MutatingHandler::$calls);
        self::assertSame(1, DB::table('tool_effects')->count());
        self::assertSame(1, DB::table('tool_idempotency')->count());
    }
}
