<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Extend\Api;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Testing\CatalogueModuleFixture;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Testing\ResolvedContexts;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Internal\CompiledToolCatalogue;
use CoreX\Tools\Internal\DenyToolPolicy;
use CoreX\Tools\Tests\Support\ToolPolicyFixture;
use CoreX\Tools\Tests\TestCase;
use CoreX\Tools\ToolServiceProvider;

final class ToolCatalogueTest extends TestCase
{
    private CatalogueModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    private ToolPolicyFixture $policy;

    protected function getPackageProviders($app): array
    {
        return [ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->modules = new CatalogueModuleFixture($this->compileTools());
        $this->tenants = new ContextFixture;
        $this->tenants->initialize($this->account('A'));
        $this->identity = new IdentityFixture;
        $this->policy = new ToolPolicyFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(ToolPolicy::class, $this->policy);
    }

    private function account(string $id): AccountRef
    {
        return new AccountRef($id, strtolower($id), 'active', [], []);
    }

    /** @return array<string, mixed> */
    private function objectSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => ['name' => ['type' => 'string']],
        ];
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
            Api::tool('notes.create', NoteHandler::class, 'notes.create', $this->objectSchema(), $this->objectSchema(), 'write', 'domain-key')
                ->titled('Create note', 'Creates a note'),
            Api::tool('notes.list', NoteHandler::class, 'notes.read', $this->objectSchema(), $this->objectSchema(), 'read', 'read-only', availability: ['discover' => false])
                ->titled('List notes', 'Lists notes'),
            Api::entity('notes.note'),
        ]);
    }

    private function context(string $account = 'A', string $principal = 'trusted-user'): RequestContext
    {
        return ResolvedContexts::make(
            tenantContext: $this->tenants->current(),
            authenticatedPrincipal: $this->identity,
            requestMetadata: new InvocationMetadata('request-1', 'v1', 'tools.discover', 'GET'),
        );
    }

    public function test_discovery_filters_activation_policy_and_per_tool_deny_metadata_cat_01(): void
    {
        $this->policy->allowDiscover = true;
        $view = $this->app->make(ToolCatalogue::class)->discover($this->context());

        self::assertCount(1, $view->descriptors);
        self::assertSame('notes.create', $view->descriptors[0]->code);
        self::assertNotContains('notes.list', array_map(static fn (ToolDescriptor $tool): string => $tool->code, $view->descriptors));

        $this->modules->enabled = false;
        $empty = $this->app->make(ToolCatalogue::class)->discover($this->context());
        self::assertSame([], $empty->descriptors);
    }

    public function test_default_policy_denies_without_breaking_boot_cat_02(): void
    {
        $policy = new DenyToolPolicy;
        $catalogue = new CompiledToolCatalogue($this->modules, $policy);

        self::assertFalse($policy->discover(
            ToolDescriptor::fromDeclaration($this->modules->registry->api[0]),
            $this->context(),
        ));
        self::assertFalse($policy->invoke(
            ToolDescriptor::fromDeclaration($this->modules->registry->api[0]),
            $this->context(),
            new JsonObject('{}'),
        ));
        self::assertSame([], $catalogue->discover($this->context())->descriptors);
        self::assertTrue($this->app->bound(ToolCatalogue::class));
    }

    public function test_discover_allow_does_not_grant_invoke_cat_03(): void
    {
        $this->policy->allowDiscover = true;
        $this->policy->allowInvoke = false;
        $descriptor = $this->app->make(ToolCatalogue::class)->discover($this->context())->descriptors[0];

        self::assertTrue($this->policy->discover($descriptor, $this->context()));
        self::assertFalse($this->policy->invoke($descriptor, $this->context(), new JsonObject('{}')));
    }

    public function test_policy_reads_trusted_context_not_payload_metadata_cat_04(): void
    {
        $this->policy->allowDiscover = true;
        $this->app->make(ToolCatalogue::class)->discover($this->context('A'));
        $this->tenants->initialize($this->account('B'));
        $this->app->make(ToolCatalogue::class)->discover($this->context('B'));

        self::assertSame(['A', 'B'], array_column($this->policy->calls, 'account'));
        self::assertSame(['trusted-user', 'trusted-user'], array_column($this->policy->calls, 'principal'));
        self::assertNotContains('forged', array_column($this->policy->calls, 'principal'));
    }

    public function test_account_changes_re_read_activation_and_policy_without_stale_state_cat_05(): void
    {
        $this->policy->allowDiscover = true;
        $catalogue = $this->app->make(ToolCatalogue::class);

        foreach (['A', 'B', 'A'] as $id) {
            $this->tenants->initialize($this->account($id));
            self::assertCount(1, $catalogue->discover($this->context($id))->descriptors);
        }

        $this->modules->enabled = false;
        self::assertSame([], $catalogue->discover($this->context('A'))->descriptors);
        self::assertContains('A', $this->modules->visitedAccounts);
        self::assertContains('B', $this->modules->visitedAccounts);
        self::assertSame('A', $this->modules->visitedAccounts[array_key_last($this->modules->visitedAccounts)]);
    }

    public function test_epoch_and_activation_changes_are_visible_on_next_discover_cat_06(): void
    {
        $this->policy->allowDiscover = true;
        $catalogue = $this->app->make(ToolCatalogue::class);

        $catalogue->discover($this->context());
        self::assertNotSame([], $this->modules->epochReads);
        self::assertSame(1, $this->modules->epochReads[0]);

        $this->modules->bumpEpoch();
        $this->modules->enabled = false;
        self::assertSame([], $catalogue->discover($this->context())->descriptors);
        self::assertContains(2, $this->modules->epochReads);
    }

    public function test_catalogue_is_deterministic_sorted_and_caps_at_one_thousand_cat_07(): void
    {
        $this->policy->allowDiscover = true;
        $tools = [];
        for ($index = 0; $index < 1001; $index++) {
            $tools[] = Api::tool(
                code: sprintf('batch.item%04d', $index),
                handler: NoteHandler::class,
                ability: 'notes.read',
                inputSchema: $this->objectSchema(),
                outputSchema: $this->objectSchema(),
                sideEffect: 'read',
                idempotency: 'read-only',
            )->titled('Tool', 'Tool');
        }

        $this->modules->registry = $this->compile($tools);
        $catalogue = $this->app->make(ToolCatalogue::class);

        try {
            $catalogue->discover($this->context());
            self::fail('Expected catalogue_limit.');
        } catch (ApiError $error) {
            self::assertSame('catalogue_limit', $error->errorCode);
        }

        $this->modules->registry = $this->compile(array_slice($tools, 0, 1000));
        $first = $catalogue->discover($this->context());
        $second = $catalogue->discover($this->context());

        self::assertCount(1000, $first->descriptors);
        self::assertSame($first->revision, $second->revision);
        self::assertSame('batch.item0000', $first->descriptors[0]->code);
        self::assertSame('batch.item0999', $first->descriptors[999]->code);
    }

    public function test_contract_hash_tracks_current_compiled_bytes_cat_08(): void
    {
        $first = ToolDescriptor::fromDeclaration($this->modules->registry->api[0]);
        $mutated = $this->modules->registry->api[0];
        $mutated->title = 'Changed title';
        $second = ToolDescriptor::fromDeclaration($mutated);

        self::assertNotSame($first->contractHash, $second->contractHash);
        self::assertSame(
            CompiledToolCatalogue::revision([$first]),
            CompiledToolCatalogue::revision([$first]),
        );
    }

    public function test_entity_capabilities_are_not_permission_grants_and_metadata_is_not_leaked_cat_09(): void
    {
        $this->policy->allowDiscover = true;
        $view = $this->app->make(ToolCatalogue::class)->discover($this->context());

        self::assertCount(1, $view->descriptors);
        $descriptor = $view->descriptors[0];
        self::assertStringNotContainsString('notes.note', json_encode($descriptor, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('fields', $descriptor->availability);
        self::assertArrayNotHasKey('capabilities', $descriptor->availability);

        $this->policy->allowDiscover = false;
        self::assertSame([], $this->app->make(ToolCatalogue::class)->discover($this->context())->descriptors);
    }
}
