<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use CoreX\Tools\Internal\DenyToolPolicy;
use CoreX\Tools\Internal\McpToolsProjection;
use CoreX\Tools\Internal\ReadOnlyToolExecutionGuard;
use CoreX\Tools\Tests\Support\ArchiveToolModule;
use CoreX\Tools\Tests\Support\AssertsRegisteredToolErrors;
use CoreX\Tools\Tests\Support\CountingHandler;
use CoreX\Tools\Tests\Support\LabToolModule;
use CoreX\Tools\Tests\Support\ToolGuardFixture;
use CoreX\Tools\Tests\Support\ToolPolicyFixture;
use CoreX\Tools\Tests\TestCase;
use CoreX\Tools\ToolsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\ModulesServiceProvider;

final class InstalledToolContractTest extends TestCase
{
    use AssertsRegisteredToolErrors;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    private ToolPolicyFixture $policy;

    /** @var array<string, mixed> */
    private array $policyFixture;

    protected function getPackageProviders($app): array
    {
        return [OperationsServiceProvider::class, ToolsServiceProvider::class, CoreServiceProvider::class, ModulesServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        CountingHandler::$calls = 0;

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

        $this->tenants = new ContextFixture;
        $this->tenants->initialize($this->account('A'));
        $this->identity = new IdentityFixture;
        $this->policy = new ToolPolicyFixture;
        $this->policyFixture = $this->loadInstallFixture('archive-lab-policy.json');
        $this->app->instance(ToolPolicy::class, $this->policy);
    }

    /** @return array<string, mixed> */
    private function loadInstallFixture(string $name): array
    {
        $path = __DIR__.'/../fixtures/tool-install/'.$name;
        $decoded = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function account(string $id): AccountRef
    {
        return new AccountRef($id, strtolower($id), 'active', [], []);
    }

    private function context(string $account = 'A'): RequestContext
    {
        return ResolvedContexts::make(
            tenantContext: $this->tenants->current(),
            authenticatedPrincipal: $this->identity,
            requestMetadata: new InvocationMetadata('request-1', 'v1', 'tools.discover', 'GET'),
        );
    }

    private function bindArchiveModule(): ArchiveToolModule
    {
        if ($this->app->bound(ModuleRegistry::class)
            && $this->app->make(ModuleRegistry::class) instanceof ArchiveToolModule) {
            /** @var ArchiveToolModule $modules */
            $modules = $this->app->make(ModuleRegistry::class);

            return $modules;
        }

        $modules = new ArchiveToolModule(ArchiveToolModule::compile());
        $this->app->instance(ModuleRegistry::class, $modules);

        return $modules;
    }

    private function archiveDescriptor(string $code): ToolDescriptor
    {
        $this->bindArchiveModule();
        $this->policy->allowDiscover = true;
        foreach ($this->app->make(ToolCatalogue::class)->discover($this->context())->descriptors as $descriptor) {
            if ($descriptor->code === $code) {
                return $descriptor;
            }
        }

        self::fail('Missing descriptor: '.$code);
    }

    public function test_boxed_archive_boots_with_explicit_policy_and_cross_account_guard_inst_02(): void
    {
        self::assertTrue($this->policyFixture['baseline_install_without_optional_tools']);
        self::assertTrue($this->policyFixture['no_mcp_transport_sdk']);
        self::assertTrue($this->policyFixture['requires_trusted_request_context']);

        $this->bindArchiveModule();
        $defaultPolicy = $this->app->make(ToolPolicy::class);
        self::assertInstanceOf(ToolPolicyFixture::class, $defaultPolicy);

        $this->policy->allowDiscover = false;
        $this->policy->allowInvoke = false;
        $denied = $this->app->make(ToolCatalogue::class)->discover($this->context());
        self::assertSame([], $denied->descriptors);

        $this->policy->allowDiscover = true;
        $this->policy->allowInvoke = true;
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->app->forgetInstance(ToolInvoker::class);
        $readDescriptor = $this->archiveDescriptor('files.read_retained');
        $readResult = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            new ToolCall(
                code: $readDescriptor->code,
                toolVersion: $readDescriptor->toolVersion,
                expectedContractHash: $readDescriptor->contractHash,
                arguments: JsonObjectFactory::fromArray(['name' => 'archive']),
                requestId: 'req-archive-read',
            ),
        );
        self::assertSame(ToolOutcome::Committed, $readResult->outcome, $readResult->errorCode ?? '');

        $writeDescriptor = $this->archiveDescriptor('maintenance.export_account');
        $writeCall = new ToolCall(
            code: $writeDescriptor->code,
            toolVersion: $writeDescriptor->toolVersion,
            expectedContractHash: $writeDescriptor->contractHash,
            arguments: JsonObjectFactory::fromArray(['name' => 'export']),
            requestId: 'req-export',
            operationKey: 'op-1',
        );
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->app->forgetInstance(ToolInvoker::class);
        $withoutProof = $this->app->make(ToolInvoker::class)->invoke($this->context(), $writeCall);
        $this->assertToolError(ToolsErrorCode::ApprovalRequired, $withoutProof->errorCode);

        $guard = new ToolGuardFixture;
        $this->app->instance(ToolExecutionGuard::class, $guard);
        $this->app->forgetInstance(ToolInvoker::class);
        $guard->approvedProofDigest = $guard->bindingDigest($writeDescriptor, $this->context(), $writeCall);
        $this->app->forgetInstance(ToolInvoker::class);
        $approved = $this->app->make(ToolInvoker::class)->invoke($this->context(), $writeCall);
        self::assertSame(ToolOutcome::Committed, $approved->outcome, $approved->errorCode ?? '');

        $this->tenants->initialize($this->account('B'));
        $foreign = $this->app->make(ToolInvoker::class)->invoke(
            $this->context('B'),
            new ToolCall(
                code: $writeDescriptor->code,
                toolVersion: $writeDescriptor->toolVersion,
                expectedContractHash: $writeDescriptor->contractHash,
                arguments: JsonObjectFactory::fromArray(['name' => 'export']),
                requestId: 'req-foreign',
                operationKey: 'op-foreign',
            ),
        );
        $this->assertToolError(ToolsErrorCode::ApprovalInvalid, $foreign->errorCode);
    }

    public function test_direct_and_mcp_projection_share_descriptor_and_result_semantics_inst_03(): void
    {
        $this->bindArchiveModule();
        $this->policy->allowDiscover = true;
        $this->policy->allowInvoke = true;
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->app->forgetInstance(ToolInvoker::class);
        $this->app->forgetInstance(ToolCatalogue::class);

        $context = $this->context();
        $descriptor = $this->archiveDescriptor('files.read_retained');
        $invoker = $this->app->make(ToolInvoker::class);
        $projection = new McpToolsProjection(
            $this->app->make(ToolCatalogue::class),
            $invoker,
        );

        $listed = $projection->listTools($context);
        self::assertSame(McpToolsProjection::PROFILE, $listed['profile']);
        self::assertSame(McpToolsProjection::PROTOCOL_VERSION, $listed['protocol']);
        $entry = array_values(array_filter(
            $listed['tools'],
            static fn (array $tool): bool => ($tool['canonicalCode'] ?? '') === 'files.read_retained',
        ))[0];
        self::assertSame($descriptor->wireName, $entry['name']);
        self::assertSame($descriptor->contractHash, $entry['contractHash']);
        self::assertTrue($entry['annotations']['readOnlyHint']);

        $direct = $invoker->invoke(
            $context,
            new ToolCall(
                code: $descriptor->code,
                toolVersion: $descriptor->toolVersion,
                expectedContractHash: $descriptor->contractHash,
                arguments: JsonObjectFactory::fromArray(['name' => 'parity']),
                requestId: 'req-parity',
            ),
        );
        $mcp = $projection->callTool(
            context: $context,
            wireName: $descriptor->wireName,
            arguments: ['name' => 'parity'],
            requestId: 'req-parity',
        );

        self::assertFalse($mcp['isError']);
        self::assertSame(
            JsonCanonical::decodeObject($direct->structuredData?->canonicalJson ?? '{}'),
            $mcp['structuredContent'],
        );

        $invalid = $projection->callTool(
            context: $context,
            wireName: 'files_read_retained__v999',
            arguments: ['name' => 'missing'],
            requestId: 'req-missing',
        );
        self::assertSame(-32602, $invalid['error']['code']);

        $this->app->forgetInstance(ToolInvoker::class);
        $badInput = $projection->callTool(
            context: $context,
            wireName: $descriptor->wireName,
            arguments: ['name' => 1],
            requestId: 'req-bad-input',
        );
        self::assertTrue($badInput['isError']);
        self::assertSame(ToolsErrorCode::InputInvalid->value, $badInput['structuredContent']['errorCode']);
    }

    public function test_tool_provider_mounts_no_routes_and_rejects_unknown_or_unguarded_mutations_inst_04(): void
    {
        $routesBefore = count(Route::getRoutes());
        $this->app->forgetInstance(ToolPolicy::class);
        $provider = new ToolsServiceProvider($this->app);
        $provider->register();
        self::assertSame($routesBefore, count(Route::getRoutes()));
        self::assertInstanceOf(DenyToolPolicy::class, $this->app->make(ToolPolicy::class));
        $this->app->instance(ToolPolicy::class, $this->policy);

        $modules = $this->bindArchiveModule();
        $this->policy->allowDiscover = true;
        $this->policy->allowInvoke = true;

        $unknown = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            new ToolCall(
                code: 'missing.tool',
                toolVersion: 'v1',
                expectedContractHash: str_repeat('a', 64),
                arguments: JsonObjectFactory::fromArray(['name' => 'x']),
                requestId: 'req-unknown',
            ),
        );
        $this->assertToolError(ToolsErrorCode::ToolUnavailable, $unknown->errorCode);

        $descriptor = $this->archiveDescriptor('files.read_retained');
        $forged = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            new ToolCall(
                code: $descriptor->code,
                toolVersion: $descriptor->toolVersion,
                expectedContractHash: str_repeat('b', 64),
                arguments: JsonObjectFactory::fromArray(['name' => 'x']),
                requestId: 'req-forged',
            ),
        );
        $this->assertToolError(ToolsErrorCode::ToolRevisionConflict, $forged->errorCode);

        $modules->enabled = false;
        $this->app->forgetInstance(ToolInvoker::class);
        $disabled = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            new ToolCall(
                code: $descriptor->code,
                toolVersion: $descriptor->toolVersion,
                expectedContractHash: $descriptor->contractHash,
                arguments: JsonObjectFactory::fromArray(['name' => 'x']),
                requestId: 'req-disabled',
            ),
        );
        $this->assertToolError(ToolsErrorCode::ToolUnavailable, $disabled->errorCode);

        $modules->enabled = true;
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->app->forgetInstance(ToolInvoker::class);
        $writeDescriptor = $this->archiveDescriptor('maintenance.export_account');
        $unguarded = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            new ToolCall(
                code: $writeDescriptor->code,
                toolVersion: $writeDescriptor->toolVersion,
                expectedContractHash: $writeDescriptor->contractHash,
                arguments: JsonObjectFactory::fromArray(['name' => 'export']),
                requestId: 'req-unguarded',
                operationKey: 'op-unguarded',
            ),
        );
        $this->assertToolError(ToolsErrorCode::ApprovalRequired, $unguarded->errorCode);
    }

    public function test_lab_cohort_remains_disjoint_from_archive_after_install_inst_02_and_05(): void
    {
        $archive = new ArchiveToolModule(ArchiveToolModule::compile());
        $lab = new LabToolModule(LabToolModule::compile());
        $this->app->instance(ModuleRegistry::class, $archive);
        $this->policy->allowDiscover = true;
        $archiveCodes = array_map(
            static fn (ToolDescriptor $tool): string => $tool->code,
            $this->app->make(ToolCatalogue::class)->discover($this->context())->descriptors,
        );
        self::assertContains('files.read_retained', $archiveCodes);
        self::assertNotContains('locale.format', $archiveCodes);

        $this->app->instance(ModuleRegistry::class, $lab);
        $this->app->forgetInstance(ToolCatalogue::class);
        $labCodes = array_map(
            static fn (ToolDescriptor $tool): string => $tool->code,
            $this->app->make(ToolCatalogue::class)->discover($this->context())->descriptors,
        );
        self::assertContains('locale.format', $labCodes);
        self::assertNotContains('files.read_retained', $labCodes);
    }
}
