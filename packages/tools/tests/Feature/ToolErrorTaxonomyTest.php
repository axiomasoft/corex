<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Testing\CatalogueModuleFixture;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Data\SchemaValidation;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Contracts\ToolSchemaValidator;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use CoreX\Tools\Internal\ReadOnlyToolExecutionGuard;
use CoreX\Tools\Internal\ToolDescriptorFactory;
use CoreX\Tools\Internal\ToolSchemaFailure;
use CoreX\Tools\Tests\Support\AssertsRegisteredToolErrors;
use CoreX\Tools\Tests\Support\CountingHandler;
use CoreX\Tools\Tests\Support\ToolGuardFixture;
use CoreX\Tools\Tests\Support\ToolPolicyFixture;
use CoreX\Tools\Tests\TestCase;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ToolErrorTaxonomyTest extends TestCase
{
    use AssertsRegisteredToolErrors;

    private CatalogueModuleFixture $modules;

    private ToolPolicyFixture $policy;

    private RequestContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        CountingHandler::$calls = 0;
        $schema = ['type' => 'object', 'additionalProperties' => true];
        $compiled = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::tool('notes.read', CountingHandler::class, 'notes.read', $schema, $schema, 'read', 'read-only')->titled('Read', 'Read'),
                Api::operation('notes.read', 'POST', '/notes', CountingHandler::class, 'notes.read', $schema, $schema),
                Api::exposeTool('notes.read', 'v1', 'notes.exposed', 'v1', 'read', 'read-only', $schema, $schema, ['name' => 'name'])->titled('Exposed', 'Exposed'),
            ]),
        ], installedPackages: []);
        $this->modules = new CatalogueModuleFixture($compiled);
        $this->policy = new ToolPolicyFixture;
        $this->policy->allowInvoke = true;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(ToolPolicy::class, $this->policy);
        $tenants = new ContextFixture;
        $tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $this->context = ResolvedContexts::make(
            tenantContext: $tenants->current(),
            authenticatedPrincipal: new IdentityFixture,
            requestMetadata: new InvocationMetadata('req-taxonomy', 'v1', 'tools.invoke', 'POST'),
        );
    }

    public function test_all_thirteen_codes_resolve_with_safe_metadata(): void
    {
        $registry = resolve(ProblemRegistry::class);
        self::assertCount(13, ToolsErrorCode::cases());
        foreach (ToolsErrorCode::cases() as $code) {
            $definition = $registry->fromWire($code->value);
            self::assertSame($code, $definition->code);
            self::assertSame('urn:packman:problem:'.$code->value, $definition->type);
            self::assertNotSame('', $definition->title);
            self::assertSame([], $definition->detailKeys);
        }
        foreach ([ToolsErrorCode::ApprovalRequired, ToolsErrorCode::ApprovalInvalid, ToolsErrorCode::Forbidden] as $code) {
            $definition = $registry->fromWire($code->value);
            self::assertSame(403, $definition->defaultStatus);
            self::assertSame(ErrorCategory::Authorization, $definition->category);
            self::assertFalse($definition->retryable);
        }
        self::assertSame(409, $registry->fromWire(ToolsErrorCode::IdempotencyConflict->value)->defaultStatus);
        self::assertSame(ErrorCategory::Conflict, $registry->fromWire(ToolsErrorCode::IdempotencyConflict->value)->category);
        self::assertFalse($registry->fromWire(ToolsErrorCode::UnknownEffect->value)->retryable);
    }

    /** @return iterable<string, array{string, bool, ToolsErrorCode}> */
    public static function schemaFailures(): iterable
    {
        foreach ([false, true] as $output) {
            yield ($output ? 'output' : 'input').'-unavailable' => ['unavailable', $output, ToolsErrorCode::SchemaUnavailable];
            yield ($output ? 'output' : 'input').'-limit' => ['limit', $output, ToolsErrorCode::SchemaLimit];
            yield ($output ? 'output' : 'input').'-text-is-not-code' => ['text', $output, ToolsErrorCode::InternalError];
        }
    }

    #[DataProvider('schemaFailures')]
    public function test_typed_schema_failures_and_generic_message_fallback(string $failure, bool $output, ToolsErrorCode $expected): void
    {
        $this->app->instance(ToolSchemaValidator::class, new class($failure, $output) implements ToolSchemaValidator
        {
            private int $admissions = 0;

            public function __construct(private readonly string $failure, private readonly bool $output) {}

            public function admit(JsonObject $schema, string $profile): SchemaHandle
            {
                $this->admissions++;

                if ($this->admissions === ($this->output ? 2 : 1)) {
                    throw match ($this->failure) {
                        'unavailable' => ToolSchemaFailure::unavailable(),
                        'limit' => ToolSchemaFailure::limit(),
                        default => new RuntimeException('schema_unavailable'),
                    };
                }

                return new SchemaHandle(digest: hash('sha256', $schema->canonicalJson), profile: $profile, localResources: ['#' => $schema->canonicalJson]);
            }

            public function validate(SchemaHandle $schema, JsonValue $value): SchemaValidation
            {
                return new SchemaValidation(valid: true, errors: []);
            }
        });
        $result = resolve(ToolInvoker::class)->invoke($this->context, $this->makeToolCall());
        $this->assertToolError($expected, $result->errorCode);
        self::assertSame(ToolOutcome::NotExecuted, $result->outcome);
        self::assertSame($output ? 1 : 0, CountingHandler::$calls);
        self::assertSame(0, ToolSchemaFailure::unavailable()->getCode());
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableBranches(): iterable
    {
        yield 'undeclared' => ['undeclared'];
        yield 'inactive module' => ['inactive'];
        yield 'policy' => ['policy'];
    }

    #[DataProvider('unavailableBranches')]
    public function test_unavailable_branches_remain_non_disclosing(string $branch): void
    {
        $this->modules->enabled = $branch !== 'inactive';
        $this->policy->allowInvoke = $branch !== 'policy';
        $call = $this->makeToolCall();

        if ($branch === 'undeclared') {
            $call = new ToolCall(code: 'missing.tool', toolVersion: 'v1', expectedContractHash: $call->expectedContractHash, arguments: $call->arguments, requestId: 'missing');
        }
        $result = resolve(ToolInvoker::class)->invoke($this->context, $call);
        $this->assertToolError(ToolsErrorCode::ToolUnavailable, $result->errorCode);
        self::assertSame(ToolOutcome::NotExecuted, $result->outcome);
        self::assertSame(0, CountingHandler::$calls);
    }

    /** @return iterable<string, array{bool}> */
    public static function operationErrors(): iterable
    {
        yield 'registered operations code unchanged' => [false];
        yield 'rate limit translated' => [true];
    }

    #[DataProvider('operationErrors')]
    public function test_exposed_operation_errors_keep_their_namespace(bool $rateLimited): void
    {
        $operations = $this->createMock(OperationInvoker::class);
        $operations->expects(self::once())->method('invoke')->willThrowException($rateLimited
            ? new TooManyRequestsHttpException
            : new ApiError(OperationsErrorCode::Forbidden, 403, 'Forbidden.'));
        $this->app->instance(OperationInvoker::class, $operations);
        $result = resolve(ToolInvoker::class)->invoke($this->context, $this->makeToolCall('notes.exposed'));
        $expected = $rateLimited ? ToolsErrorCode::RateLimited->value : OperationsErrorCode::Forbidden->value;
        self::assertSame($expected, $result->errorCode);
        self::assertSame($expected, resolve(ProblemRegistry::class)->fromWire($result->errorCode)->code->value());
        self::assertSame(ToolOutcome::NotExecuted, $result->outcome);
    }

    public function test_default_guard_refuses_mutation_before_effect(): void
    {
        $schema = ['type' => 'object'];
        $declaration = Api::tool('notes.write', CountingHandler::class, 'notes.write', $schema, $schema, 'write', 'provider-key')->titled('Write', 'Write');
        $compiled = resolve(ModuleCompilation::class)->compile([ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$declaration])], installedPackages: []);
        $descriptor = ToolDescriptorFactory::fromDeclaration($compiled->api[0]);
        $call = new ToolCall(code: 'notes.write', toolVersion: 'v1', expectedContractHash: $descriptor->contractHash, arguments: JsonObjectFactory::fromArray(['name' => 'alpha']), requestId: 'guard-taxonomy', operationKey: 'guard-key');
        $called = false;
        $result = (new ReadOnlyToolExecutionGuard)->execute($descriptor, $this->context, $call, static function () use (&$called): ToolResult {
            $called = true;

            throw new RuntimeException('Unexpected effect.');
        });
        $this->assertToolError(ToolsErrorCode::ApprovalRequired, $result->errorCode);
        self::assertSame(ToolOutcome::NotExecuted, $result->outcome);
        self::assertFalse($called);
        self::assertSame('guard-key', $result->operationKey);
    }

    public function test_consumer_guard_keeps_unknown_effect_and_operation_key(): void
    {
        $schema = ['type' => 'object'];
        $declaration = Api::tool('notes.provider', CountingHandler::class, 'notes.write', $schema, $schema, 'write', 'provider-key')->titled('Provider', 'Provider');
        $compiled = resolve(ModuleCompilation::class)->compile([ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$declaration])], installedPackages: []);
        $descriptor = ToolDescriptorFactory::fromDeclaration($compiled->api[0]);
        $call = new ToolCall(code: 'notes.provider', toolVersion: 'v1', expectedContractHash: $descriptor->contractHash, arguments: JsonObjectFactory::fromArray(['name' => 'alpha']), requestId: 'provider-taxonomy', operationKey: 'provider-key');
        $guard = new ToolGuardFixture;
        $result = $guard->execute($descriptor, $this->context, $call, static function (): ToolResult {
            throw new RuntimeException('Uncertain external effect.');
        });
        $this->assertToolError(ToolsErrorCode::UnknownEffect, $result->errorCode);
        self::assertSame(ToolOutcome::Unknown, $result->outcome);
        self::assertSame('provider-key', $result->operationKey);
        self::assertSame(1, $guard->effectCount);
    }

    public function test_guard_cannot_claim_a_fresh_commit_without_invoking_effect(): void
    {
        $guard = $this->createMock(ToolExecutionGuard::class);
        $guard->expects(self::once())->method('execute')->willReturn(new ToolResult(outcome: ToolOutcome::Committed, structuredData: null, errorCode: null, operationKey: 'tax-key', replayed: false));
        $this->app->instance(ToolExecutionGuard::class, $guard);
        $result = resolve(ToolInvoker::class)->invoke($this->context, $this->makeToolCall());
        $this->assertToolError(ToolsErrorCode::InternalError, $result->errorCode);
        self::assertSame(ToolOutcome::NotExecuted, $result->outcome);
        self::assertSame(0, CountingHandler::$calls);
    }

    private function makeToolCall(string $code = 'notes.read'): ToolCall
    {
        $declaration = array_values(array_filter($this->modules->registry->api, static fn (Api $api): bool => $api->toolCode === $code))[0];
        $descriptor = ToolDescriptorFactory::fromDeclaration($declaration);

        return new ToolCall(code: $code, toolVersion: $descriptor->toolVersion, expectedContractHash: $descriptor->contractHash, arguments: JsonObjectFactory::fromArray(['name' => 'alpha']), requestId: 'req-taxonomy', operationKey: 'tax-key');
    }
}
