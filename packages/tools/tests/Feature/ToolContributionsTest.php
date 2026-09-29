<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ResolvedContexts;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Internal\ReadOnlyToolExecutionGuard;
use CoreX\Tools\Tests\Support\ArchiveToolModule;
use CoreX\Tools\Tests\Support\CountingHandler;
use CoreX\Tools\Tests\Support\LabToolModule;
use CoreX\Tools\Tests\Support\ToolGuardFixture;
use CoreX\Tools\Tests\Support\ToolPolicyFixture;
use CoreX\Tools\Tests\TestCase;
use CoreX\Tools\ToolServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ToolContributionsTest extends TestCase
{
    private ContextFixture $tenants;

    private IdentityFixture $identity;

    private ToolPolicyFixture $policy;

    /** @var array<string, mixed> */
    private array $externalMap;

    /** @var array<string, mixed> */
    private array $selectedContributions;

    protected function getPackageProviders($app): array
    {
        return [ApiServiceProvider::class, ToolServiceProvider::class];
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
        $this->externalMap = $this->loadFixture('external-control-plane-map.json');
        $this->selectedContributions = $this->loadFixture('selected-contributions.json');
        $this->app->instance(ToolPolicy::class, $this->policy);
    }

    /** @return array<string, mixed> */
    private function loadFixture(string $name): array
    {
        $path = __DIR__.'/../fixtures/tool-contribution/'.$name;
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

    /** @param list<ToolDescriptor> $descriptors */
    private function codes(array $descriptors): array
    {
        return array_map(static fn (ToolDescriptor $tool): string => $tool->code, $descriptors);
    }

    /** @return array<string, ToolDescriptor> */
    private function descriptorIndex(ModuleRegistry $modules): array
    {
        $this->app->instance(ModuleRegistry::class, $modules);
        $this->policy->allowDiscover = true;
        $view = $this->app->make(ToolCatalogue::class)->discover($this->context());
        $index = [];
        foreach ($view->descriptors as $descriptor) {
            $index[$descriptor->code] = $descriptor;
        }

        return $index;
    }

    /** @return list<array<string, mixed>> */
    private function contributionsForPhase(string $phase): array
    {
        return array_values(array_filter(
            $this->selectedContributions['contributions'],
            static fn (array $row): bool => ($row['phase'] ?? '') === $phase && ($row['disposition'] ?? '') === 'included',
        ));
    }

    public function test_external_control_plane_tools_never_tenant_exposed_contrib_01(): void
    {
        $externalCodes = array_column($this->externalMap['tools'], 'code');
        self::assertCount(19, $externalCodes);
        self::assertSame('control-plane', $this->externalMap['plane']);
        self::assertSame('external-owner', $this->externalMap['disposition']);

        foreach ([new ArchiveToolModule(ArchiveToolModule::compile()), new LabToolModule(LabToolModule::compile())] as $modules) {
            $discovered = $this->codes(array_values($this->descriptorIndex($modules)));
            self::assertEmpty(array_intersect($externalCodes, $discovered));
        }

        $rootWriters = array_filter(
            $externalCodes,
            static fn (string $code): bool => str_starts_with($code, 'identity.')
                && in_array($code, ['identity.grant_membership', 'identity.revoke_membership', 'identity.resync_account_users', 'identity.invalidate_switcher_cache'], true),
        );
        self::assertNotEmpty($rootWriters);
        self::assertNotContains('identity.grant_membership', $this->codes(array_values($this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile())))));
    }

    public function test_selected_p3_receipts_map_to_actual_mechanics_contrib_02(): void
    {
        $index = $this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile()));
        foreach ($this->contributionsForPhase('P3') as $row) {
            self::assertArrayHasKey($row['tool_code'], $index);
            $descriptor = $index[$row['tool_code']];
            self::assertSame($row['side_effect'], $descriptor->sideEffect);
            self::assertSame($row['ability'], $descriptor->ability);
            self::assertSame($row['contract_hash'], $descriptor->contractHash);
            self::assertNotSame('identity.grant_membership', $descriptor->code);
        }
    }

    public function test_only_approved_p4_receipts_add_finite_contributions_contrib_03(): void
    {
        $rows = $this->contributionsForPhase('P4');
        self::assertCount(1, $rows);
        self::assertSame('panel/1', $rows[0]['contract']);
        $index = $this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile()));
        self::assertArrayHasKey('panels.render_slot', $index);
        self::assertNotContains('media.attach', array_keys($index));
    }

    public function test_selected_p5_wakeup_binding_without_control_plane_edge_contrib_04(): void
    {
        $rows = $this->contributionsForPhase('P5');
        self::assertCount(1, $rows);
        self::assertSame('wakeup/1', $rows[0]['contract']);
        $index = $this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile()));
        self::assertArrayHasKey('workflow.wakeup_local', $index);
        self::assertNotContains('tenancy.get_account_status', array_keys($index));
    }

    public function test_p6_search_import_limits_bound_without_bypass_contrib_05(): void
    {
        $rows = $this->contributionsForPhase('P6');
        self::assertCount(1, $rows);
        self::assertSame('search/1', $rows[0]['contract']);
        $index = $this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile()));
        self::assertSame('read', $index['search.query']->sideEffect);
        self::assertSame('read-only', $index['search.query']->idempotency);
    }

    public function test_p7_utilities_keep_optional_semantics_contrib_06(): void
    {
        $index = $this->descriptorIndex(new LabToolModule(LabToolModule::compile()));
        foreach ($this->contributionsForPhase('P7') as $row) {
            self::assertArrayHasKey($row['tool_code'], $index);
            self::assertSame($row['contract_hash'], $index[$row['tool_code']]->contractHash);
            self::assertNotSame('RUB', $index[$row['tool_code']]->code);
        }
    }

    public function test_archive_and_lab_cohorts_are_independent_contrib_07(): void
    {
        $archive = $this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile()));
        $lab = $this->descriptorIndex(new LabToolModule(LabToolModule::compile()));

        self::assertContains('files.read_retained', array_keys($archive));
        self::assertNotContains('locale.format', array_keys($archive));
        self::assertContains('locale.format', array_keys($lab));
        self::assertNotContains('files.read_retained', array_keys($lab));

        $this->app->instance(ModuleRegistry::class, new ArchiveToolModule(ArchiveToolModule::compile()));
        $this->policy->allowDiscover = true;
        $this->policy->allowInvoke = true;
        $this->app->instance(ToolExecutionGuard::class, new ReadOnlyToolExecutionGuard);
        $this->app->forgetInstance(ToolInvoker::class);
        $this->app->forgetInstance(ToolCatalogue::class);
        $readDescriptor = $archive['files.read_retained'];
        $readCall = new ToolCall(
            code: $readDescriptor->code,
            toolVersion: $readDescriptor->toolVersion,
            expectedContractHash: $readDescriptor->contractHash,
            arguments: JsonObject::fromArray(['name' => 'archive']),
            requestId: 'req-archive-read',
        );
        $readResult = $this->app->make(ToolInvoker::class)->invoke($this->context(), $readCall);
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $readResult->outcome);

        $this->app->instance(ModuleRegistry::class, new LabToolModule(LabToolModule::compile()));
        $guard = new ToolGuardFixture;
        $this->app->instance(ToolExecutionGuard::class, $guard);
        $this->app->forgetInstance(ToolInvoker::class);
        $writeDescriptor = $lab['tasks.complete'];
        $writeCall = new ToolCall(
            code: $writeDescriptor->code,
            toolVersion: $writeDescriptor->toolVersion,
            expectedContractHash: $writeDescriptor->contractHash,
            arguments: JsonObject::fromArray(['name' => 'lab-task']),
            requestId: 'req-lab-write',
            operationKey: 'lab-task-1',
        );
        $guard->approvedProofDigest = $guard->bindingDigest($writeDescriptor, $this->context(), $writeCall);
        $writeResult = $this->app->make(ToolInvoker::class)->invoke($this->context(), $writeCall);
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $writeResult->outcome);
    }

    public function test_included_rows_have_exact_tuple_and_hash_receipt_contrib_08(): void
    {
        $archive = $this->descriptorIndex(new ArchiveToolModule(ArchiveToolModule::compile()));
        $lab = $this->descriptorIndex(new LabToolModule(LabToolModule::compile()));
        $included = array_filter(
            $this->selectedContributions['contributions'],
            static fn (array $row): bool => ($row['disposition'] ?? '') === 'included',
        );
        self::assertCount(11, $included);

        foreach ($included as $row) {
            $index = ($row['cohort'] ?? '') === 'lab' ? $lab : $archive;
            self::assertArrayHasKey($row['tool_code'], $index, $row['id']);
            $descriptor = $index[$row['tool_code']];
            self::assertSame($row['module'], $descriptor->module);
            self::assertSame($row['side_effect'], $descriptor->sideEffect);
            self::assertSame($row['idempotency'], $descriptor->idempotency);
            self::assertSame($row['contract_hash'], $descriptor->contractHash);
            self::assertNotEmpty($row['owner']);
            self::assertNotEmpty($row['producer_receipt']);
        }
    }
}
