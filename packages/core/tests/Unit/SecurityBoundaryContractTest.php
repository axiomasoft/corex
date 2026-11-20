<?php

declare(strict_types=1);

namespace CoreX\Tests\Unit;

use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Enums\ImpersonationRestrictedAction;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ImpersonationRestrictedException;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Internal\ImpersonationGuard;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Exceptions\InvalidScopePayload;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Data\Internal\DenyDataScopeAuthorizer;
use CoreX\Errors\Enums\CoreErrorCode;
use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Schema\Exceptions\SchemaSetException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityBoundaryContractTest extends TestCase
{
    public function test_each_authorizer_preserves_the_admission_verdict_and_reason(): void
    {
        $scope = $this->scope();
        $decision = (new DenyDataScopeAuthorizer)->authorize($scope, new RecordRef('contact', 'record-1'), new FieldSelection(['name']), FieldUse::Write);
        self::assertFalse($decision->allowed);
        self::assertSame('deny.default', $decision->reasonCode);

        $authorizer = new CallableDataScopeAuthorizer(function (DataScope $actual, RecordRef $record, FieldSelection $selection, FieldUse $use) use ($scope): bool {
            self::assertSame($scope, $actual);
            self::assertSame('contact', $record->entity);
            self::assertSame('record-1', $record->id);
            self::assertSame(['name', 'email'], $selection->fields);

            return $use === FieldUse::Write;
        });
        foreach (['write' => [true, 'callable.allowed'], 'read' => [false, 'callable.denied']] as $action => [$allowed, $reason]) {
            $decision = $authorizer->authorize($scope, new RecordRef('contact', 'record-1'), new FieldSelection(['name', 'email']), FieldUse::from($action));
            self::assertSame($allowed, $decision->allowed);
            self::assertSame($reason, $decision->reasonCode);
        }
    }

    #[DataProvider('invalidOpaqueValues')]
    public function test_control_bytes_and_overlong_scope_and_token_identities_are_rejected(string $value): void
    {
        foreach (['accountId', 'workspaceId', 'bindingId', 'principalId'] as $key) {
            $args = get_object_vars($this->scope());
            $args[$key] = $value;

            try {
                new DataScope(...$args);
                self::fail('Invalid scope identity admitted: '.$key);
            } catch (InvalidArgumentException $exception) {
                self::assertStringStartsWith('Invalid ', $exception->getMessage());
            }
        }
        foreach (['operationKey', 'expectedRecordVersion'] as $key) {
            $args = ['operationKey' => 'op', 'payloadHash' => str_repeat('a', 64), 'expectedRecordVersion' => 'v1'];
            $args[$key] = $value;

            try {
                new WriteToken(...$args);
                self::fail('Invalid token identity admitted: '.$key);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid '.($key === 'operationKey' ? 'operation key' : 'expected record version').'.', $exception->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Record identifier must be a bounded opaque value.');
        new RecordRef(entity: 'contact', id: $value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidOpaqueValues(): iterable
    {
        yield 'empty' => [''];
        yield '129 bytes' => [str_repeat('a', 129)];
        foreach ([0, 1, 10, 31, 127] as $byte) {
            yield 'control '.$byte => ['before'.chr($byte).'after'];
        }
    }

    public function test_opaque_boundaries_and_reason_code_grammar_accept_the_exact_limits(): void
    {
        $id = str_repeat('a', 128);
        $scope = new DataScope($id, $id, $id, $id, 1, $id, str_repeat('a', 64), 'user', $id);
        self::assertSame($id, $scope->workspaceId);
        self::assertSame($id, (new RecordRef($id, $id))->id);
        self::assertSame($id, (new WriteToken($id, str_repeat('b', 64), $id))->operationKey);
        foreach (['a', 'allow.0_-', $id] as $reason) {
            self::assertSame($reason, (new DataScopeAccessDecision(false, $reason))->reasonCode);
        }
    }

    #[DataProvider('invalidReasons')]
    public function test_access_reason_codes_cannot_cross_the_declared_wire_grammar(string $reason): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access decision reason code is invalid.');
        new DataScopeAccessDecision(true, $reason);
    }

    /** @return iterable<array{string}> */
    public static function invalidReasons(): iterable
    {
        foreach (['', str_repeat('a', 129), '1allow', 'Allow', 'allow/deny', "allow\n", "allow\0"] as $value) {
            yield [$value];
        }
    }

    public function test_impersonation_restrictions_apply_to_every_privileged_action_only_while_active(): void
    {
        $service = $this->createMock(ImpersonationService::class);
        $state = new ImpersonationState('grant', 'staff', 'target', new DateTimeImmutable('2030-01-01T00:00:00Z'));
        $service->method('state')->willReturnOnConsecutiveCalls(null, null, $state, $state);
        $guard = new ImpersonationGuard($service);
        self::assertFalse($guard->denies(ImpersonationRestrictedAction::Payment));
        $guard->assertAllowed(ImpersonationRestrictedAction::Payment);
        self::assertTrue($guard->denies(ImpersonationRestrictedAction::Payment));

        try {
            $guard->assertAllowed(ImpersonationRestrictedAction::Payment);
            self::fail('Active impersonation admitted a payment.');
        } catch (ImpersonationRestrictedException $exception) {
            self::assertSame('Action restricted under impersonation: Payment (B-11 §5.9 п.3).', $exception->getMessage());
        }
        foreach (ImpersonationRestrictedAction::cases() as $action) {
            $exception = ImpersonationRestrictedException::forAction($action);
            self::assertSame('Action restricted under impersonation: '.$action->name.' (B-11 §5.9 п.3).', $exception->getMessage());
            self::assertSame(CoreErrorCode::ImpersonationRestrictedException->value, $exception->errorDescriptor()->code);
            self::assertSame(ErrorCategory::Authorization, $exception->errorDescriptor()->category);
            self::assertFalse($exception->errorDescriptor()->retryable);
        }
        self::assertSame('Model write blocked under impersonation: App\\Models\\Contact. Use the current scoped ImpersonatedModelWriteGuard instance allowing() method for an intentional mutation.', ImpersonationRestrictedException::forModelWrite('App\\Models\\Contact')->getMessage());
    }

    public function test_context_mismatch_diagnostics_keep_both_identities_and_safe_error_descriptors(): void
    {
        $stale = new ScopeStale('Work scope is stale.');
        self::assertSame(CoreErrorCode::ScopeStale->value, $stale->errorDescriptor()->code);
        self::assertSame(ErrorCategory::Conflict, $stale->errorDescriptor()->category);
        self::assertSame([], $stale->errorDescriptor()->details);
        self::assertFalse($stale->errorDescriptor()->retryable);
        $central = new CentralContextException;
        self::assertSame('No tenant context is active — this call must run inside an initialized tenant (not the central/root scope).', $central->getMessage());
        self::assertSame(CoreErrorCode::CentralContextException->value, $central->errorDescriptor()->code);
        self::assertSame(ErrorCategory::Authorization, $central->errorDescriptor()->category);
        self::assertFalse($central->errorDescriptor()->retryable);
    }

    private function scope(): DataScope
    {
        return new DataScope('test', 'account', null, 'resource', 1, 'incarnation', str_repeat('a', 64), 'user', 'actor');
    }

    public function test_missing_version_and_untyped_optional_workspace_are_safe_payload_failures(): void
    {
        $codec = new ArrayDataScopeCodec;
        $payload = $codec->encode(scope: $this->scope());
        foreach (['version', 'workspace_id'] as $key) {
            $invalid = $payload;

            if ($key === 'version') {
                unset($invalid[$key]);
            } else {
                $invalid[$key] = 7;
            }

            try {
                $codec->decode(payload: $invalid);
                self::fail('Malformed durable payload was admitted.');
            } catch (InvalidScopePayload $error) {
                self::assertSame('Data scope payload key ['.$key.'] is missing or malformed.', $error->getMessage());
                self::assertSame(CoreErrorCode::InvalidScopePayload->value, $error->errorDescriptor()->code);
                self::assertSame(ErrorCategory::Validation, $error->errorDescriptor()->category);
                self::assertFalse($error->errorDescriptor()->retryable);
            }
        }
        $error = SchemaSetException::invalidSet(id: 'fixture/package:tenant');
        self::assertSame(\Packman\Errors\Enums\CoreErrorCode::SchemaInvalidSet->value, $error->errorDescriptor()->code);
        self::assertSame(ErrorCategory::Internal, $error->errorDescriptor()->category);
        self::assertFalse($error->errorDescriptor()->retryable);
    }
}
