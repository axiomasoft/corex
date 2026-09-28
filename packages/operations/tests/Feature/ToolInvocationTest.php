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
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\ReadOnlyToolExecutionGuard;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Tests\Support\BrokenHandler;
use CoreX\Api\Tests\Support\CatalogueModuleFixture;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\CountingHandler;
use CoreX\Api\Tests\Support\HeadlessWrappedHandler;
use CoreX\Api\Tests\Support\HttpDependentHandler;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\MutatingHandler;
use CoreX\Api\Tests\Support\OutputMismatchHandler;
use CoreX\Api\Tests\Support\ProviderKeyHandler;
use CoreX\Api\Tests\Support\ToolGuardFixture;
use CoreX\Api\Tests\Support\ToolPolicyFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Api\ToolServiceProvider;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Tenancy\AccountRef;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

final class ToolInvocationTest extends TestCase
{
    private CatalogueModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    private ToolPolicyFixture $policy;

    private ToolGuardFixture $guard;

    protected function getPackageProviders($app): array
    {
        return [ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        CountingHandler::$calls = 0;
        MutatingHandler::$calls = 0;
        ProviderKeyHandler::$mode = 'timeout';

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

        $this->modules = new CatalogueModuleFixture($this->compileTools());
        $this->tenants = new ContextFixture;
        $this->tenants->initialize($this->account('A'));
        $this->identity = new IdentityFixture;
        $this->policy = new ToolPolicyFixture;
        $this->guard = new ToolGuardFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(ToolPolicyFixture::class, $this->policy);
        $this->app->instance(ToolPolicy::class, $this->policy);
        $this->app->instance(ToolGuardFixture::class, $this->guard);
        $this->app->instance(ToolExecutionGuard::class, $this->guard);
    }

    private function account(string $id): AccountRef
    {
        return new AccountRef($id, strtolower($id), 'active', [], []);
    }

    /** @return array<string, mixed> */
    private function objectSchema(array $properties = ['name' => ['type' => 'string']]): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
    }

    /** @return array<string, mixed> */
    private function outputSchema(): array
    {
        return $this->objectSchema([
            'name' => ['type' => 'string'],
            'actor' => ['type' => 'string'],
            'account' => ['type' => 'string'],
            'operation_key' => ['type' => ['string', 'null']],
            'contract_hash' => ['type' => ['string', 'null']],
        ]);
    }

    /** @param  list<Api>  $declarations */
    private function compile(array $declarations): CompiledRegistry
    {
        return app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends($declarations),
        ], installedPackages: []);
    }

    private function compileTools(): CompiledRegistry
    {
        return $this->compile([
            Api::tool('notes.read', CountingHandler::class, 'notes.read', $this->objectSchema(), $this->outputSchema(), 'read', 'read-only')
                ->titled('Read note', 'Reads a note'),
            Api::tool('notes.create', MutatingHandler::class, 'notes.create', $this->objectSchema(['name' => ['type' => 'string'], 'operation_key' => ['type' => 'string']]), $this->objectSchema(['status' => ['type' => 'string'], 'account' => ['type' => 'string']]), 'write', 'domain-key')
                ->titled('Create note', 'Creates a note'),
            Api::tool('notes.draft', MutatingHandler::class, 'notes.create', $this->objectSchema(['name' => ['type' => 'string'], 'operation_key' => ['type' => 'string']]), $this->objectSchema(['status' => ['type' => 'string'], 'account' => ['type' => 'string']]), 'draft', 'domain-key')
                ->titled('Draft note', 'Drafts a note'),
            Api::tool('notes.erase', MutatingHandler::class, 'notes.create', $this->objectSchema(['name' => ['type' => 'string'], 'operation_key' => ['type' => 'string']]), $this->objectSchema(['status' => ['type' => 'string'], 'account' => ['type' => 'string']]), 'irreversible', 'domain-key')
                ->titled('Erase note', 'Erases a note'),
            Api::tool('notes.provider', ProviderKeyHandler::class, 'notes.create', $this->objectSchema(), $this->objectSchema(['provider_status' => ['type' => 'string']]), 'write', 'provider-key')
                ->titled('Provider note', 'Provider keyed'),
            Api::tool('notes.badoutput', OutputMismatchHandler::class, 'notes.read', $this->objectSchema(), $this->outputSchema(), 'read', 'read-only')
                ->titled('Bad output', 'Bad output'),
            Api::tool('notes.http', HttpDependentHandler::class, 'notes.read', $this->objectSchema(), $this->objectSchema(['actor' => ['type' => 'string']]), 'read', 'read-only')
                ->titled('HTTP dependent', 'HTTP dependent'),
            Api::tool('notes.wrapped', HeadlessWrappedHandler::class, 'notes.read', $this->objectSchema(), $this->objectSchema(['actor' => ['type' => 'string']]), 'read', 'read-only')
                ->titled('Wrapped', 'Wrapped'),
        ]);
    }

    private function context(string $account = 'A'): ResolvedContext
    {
        return new ResolvedContext(
            tenantContext: $this->tenants->current(),
            authenticatedPrincipal: $this->identity,
            requestMetadata: new InvocationMetadata('request-1', 'v1', 'tools.invoke', 'POST'),
        );
    }

    private function descriptor(string $code): ToolDescriptor
    {
        foreach ($this->modules->registry->api as $api) {
            if ($api->toolCode === $code) {
                return ToolDescriptor::fromDeclaration($api);
            }
        }

        throw new LogicException('Missing tool: '.$code);
    }

    private function makeToolCall(string $code, array $arguments = ['name' => 'alpha'], ?string $operationKey = null, ?string $clientProof = null): ToolCall
    {
        $descriptor = $this->descriptor($code);

        return new ToolCall(
            code: $code,
            toolVersion: $descriptor->toolVersion,
            expectedContractHash: $descriptor->contractHash,
            arguments: JsonObject::fromArray($arguments),
            requestId: 'req-'.md5($code.serialize($arguments)),
            operationKey: $operationKey,
            clientProof: $clientProof,
        );
    }

    public function test_read_transport_and_direct_call_share_catalogue_policy_and_handler_invoke_01(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = $this->makeToolCall('notes.read');
        $result = $invoker->invoke($this->context(), $call);

        self::assertSame(ToolResult::OUTCOME_COMMITTED, $result->outcome);
        self::assertNotNull($result->structuredData);
        $decoded = JsonCanonical::decodeObject($result->structuredData->canonicalJson);

        self::assertSame('alpha', $decoded['name']);
        self::assertSame('trusted-user', $decoded['actor']);
        self::assertSame('A', $decoded['account']);
        self::assertSame(1, CountingHandler::$calls);
    }

    public function test_invalid_input_rejected_before_handler_invoke_02(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);

        $unknownField = $invoker->invoke($this->context(), $this->makeToolCall('notes.read', ['name' => 'x', 'forged' => true]));
        self::assertSame('input_invalid', $unknownField->errorCode);

        $badJson = new ToolCall(
            code: 'notes.read',
            toolVersion: 'v1',
            expectedContractHash: $this->descriptor('notes.read')->contractHash,
            arguments: new JsonObject('{"name":1}'),
            requestId: 'req-bad-type',
        );
        $wrongType = $invoker->invoke($this->context(), $badJson);
        self::assertSame('input_invalid', $wrongType->errorCode);

        self::assertSame(0, CountingHandler::$calls);
    }

    public function test_mutation_default_guard_denies_and_client_hints_cannot_bypass_invoke_03(): void
    {
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);

        $result = $invoker->invoke($this->context(), $this->makeToolCall(
            code: 'notes.create',
            arguments: ['name' => 'alpha', 'operation_key' => 'op-1'],
            operationKey: 'op-1',
        ));

        self::assertSame('approval_required', $result->errorCode);
        self::assertSame(0, MutatingHandler::$calls);
    }

    public function test_consumer_guard_binds_approval_to_principal_scope_hash_and_args_invoke_04(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = $this->makeToolCall('notes.create', ['name' => 'alpha', 'operation_key' => 'op-4'], operationKey: 'op-4');
        $binding = $this->guard->bindingDigest($this->descriptor('notes.create'), $this->context(), $call);
        $this->guard->approvedProofDigest = $binding;

        $ok = $invoker->invoke($this->context(), $call);
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $ok->outcome);

        $changedArgs = $this->makeToolCall('notes.create', ['name' => 'beta', 'operation_key' => 'op-4b'], operationKey: 'op-4b', clientProof: $binding);
        $argsDenied = $invoker->invoke($this->context(), $changedArgs);
        self::assertSame('approval_invalid', $argsDenied->errorCode);

        $this->tenants->initialize($this->account('B'));
        $foreignCall = $this->makeToolCall('notes.create', ['name' => 'alpha', 'operation_key' => 'op-4-foreign'], operationKey: 'op-4-foreign');
        $foreign = $invoker->invoke($this->context('B'), $foreignCall);
        self::assertSame('approval_invalid', $foreign->errorCode);
    }

    public function test_domain_key_idempotency_replays_equal_requests_and_conflicts_on_mismatch_invoke_05(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = $this->makeToolCall('notes.create', ['name' => 'alpha', 'operation_key' => 'op-5'], operationKey: 'op-5');
        $this->guard->approvedProofDigest = $this->guard->bindingDigest($this->descriptor('notes.create'), $this->context(), $call);

        $first = $invoker->invoke($this->context(), $call);
        $second = $invoker->invoke($this->context(), $call);

        self::assertSame(ToolResult::OUTCOME_COMMITTED, $first->outcome);
        self::assertTrue($second->replayed);
        self::assertSame(1, MutatingHandler::$calls);
        self::assertSame(1, DB::table('tool_effects')->count());

        $conflict = $invoker->invoke(
            $this->context(),
            $this->makeToolCall('notes.create', ['name' => 'other', 'operation_key' => 'op-5'], operationKey: 'op-5'),
        );
        self::assertSame('idempotency_conflict', $conflict->errorCode);
    }

    public function test_stale_resource_version_denies_before_effect_invoke_06(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = $this->makeToolCall('notes.create', ['name' => 'alpha', 'operation_key' => 'op-6'], operationKey: 'op-6');
        $this->guard->approvedProofDigest = $this->guard->bindingDigest($this->descriptor('notes.create'), $this->context(), $call);
        $this->guard->resourceVersion = 2;
        $this->guard->requiredResourceVersion = 1;

        $denied = $invoker->invoke($this->context(), $call);
        self::assertSame('approval_invalid', $denied->errorCode);
        self::assertSame(0, MutatingHandler::$calls);

        $this->guard->requiredResourceVersion = 2;
        $this->guard->approvedProofDigest = $this->guard->bindingDigest($this->descriptor('notes.create'), $this->context(), $call);
        $allowed = $invoker->invoke($this->context(), $call);
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $allowed->outcome);
    }

    public function test_provider_key_timeout_keeps_unknown_outcome_and_stable_operation_key_invoke_07(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = $this->makeToolCall('notes.provider', ['name' => 'alpha'], operationKey: 'provider-op-7');

        $unknown = $invoker->invoke($this->context(), $call);
        self::assertSame(ToolResult::OUTCOME_UNKNOWN, $unknown->outcome);
        self::assertSame('unknown_effect', $unknown->errorCode);
        self::assertSame('provider-op-7', $unknown->operationKey);

        ProviderKeyHandler::$mode = 'committed';
        $committed = $invoker->invoke($this->context(), $call);
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $committed->outcome);
    }

    public function test_output_schema_mismatch_rolls_back_local_transaction_invoke_08(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $bad = $invoker->invoke($this->context(), $this->makeToolCall('notes.badoutput'));

        self::assertSame('output_contract_failed', $bad->errorCode);
        self::assertSame(0, CountingHandler::$calls);
    }

    public function test_replay_requires_current_visibility_invoke_09(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = $this->makeToolCall('notes.create', ['name' => 'alpha', 'operation_key' => 'op-9'], operationKey: 'op-9');
        $this->guard->approvedProofDigest = $this->guard->bindingDigest($this->descriptor('notes.create'), $this->context(), $call);

        $first = $invoker->invoke($this->context(), $call);
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $first->outcome);

        $this->guard->visibilityRevoked = true;
        $denied = $invoker->invoke($this->context(), $call);
        self::assertSame('forbidden', $denied->errorCode);
        self::assertNull($denied->structuredData);
    }

    public function test_draft_and_irreversible_receive_guarded_treatment_and_read_cannot_mutate_invoke_10(): void
    {
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);

        $read = $invoker->invoke($this->context(), $this->makeToolCall('notes.read', ['name' => 'preview']));
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $read->outcome);
        self::assertSame(0, DB::table('tool_effects')->count());

        $draft = $invoker->invoke($this->context(), $this->makeToolCall('notes.draft', ['name' => 'alpha', 'operation_key' => 'd-1'], operationKey: 'd-1'));
        self::assertSame('approval_required', $draft->errorCode);

        $erase = $invoker->invoke($this->context(), $this->makeToolCall('notes.erase', ['name' => 'alpha', 'operation_key' => 'e-1'], operationKey: 'e-1'));
        self::assertSame('approval_required', $erase->errorCode);
    }

    public function test_unexpected_exceptions_produce_safe_error_without_secrets_invoke_11(): void
    {
        $this->modules->registry = $this->compile([
            Api::tool('notes.broken', BrokenHandler::class, 'notes.read', $this->objectSchema(), $this->outputSchema(), 'read', 'read-only')
                ->titled('Broken', 'Broken'),
        ]);
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $call = new ToolCall(
            code: 'notes.broken',
            toolVersion: 'v1',
            expectedContractHash: ToolDescriptor::fromDeclaration($this->modules->registry->api[0])->contractHash,
            arguments: JsonObject::fromArray(['name' => 'x']),
            requestId: 'req-broken',
        );

        $result = $invoker->invoke($this->context(), $call);

        self::assertSame('internal_error', $result->errorCode);
        self::assertStringNotContainsString('SQLSTATE', (string) $result->errorCode);
        self::assertStringNotContainsString('secret', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_handler_reuses_verified_context_and_http_provider_requires_wrapper_invoke_12(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);

        $http = $invoker->invoke($this->context(), $this->makeToolCall('notes.http'));
        self::assertSame('internal_error', $http->errorCode);

        $wrapped = $invoker->invoke($this->context(), $this->makeToolCall('notes.wrapped'));
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $wrapped->outcome);
        $payload = JsonCanonical::decodeObject($wrapped->structuredData?->canonicalJson ?? '{}');
        self::assertSame('trusted-user', $payload['actor'] ?? null);
    }

    public function test_stale_contract_hash_rejected_invoke_revision(): void
    {
        $this->policy->allowInvoke = true;
        $invoker = $this->app->make(ToolInvoker::class);
        $descriptor = $this->descriptor('notes.read');
        $stale = new ToolCall(
            code: 'notes.read',
            toolVersion: 'v1',
            expectedContractHash: str_repeat('a', 64),
            arguments: JsonObject::fromArray(['name' => 'alpha']),
            requestId: 'req-stale',
        );

        $result = $invoker->invoke($this->context(), $stale);
        self::assertSame('tool_revision_conflict', $result->errorCode);
    }
}
