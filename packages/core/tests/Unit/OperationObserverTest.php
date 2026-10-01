<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Observability\Data\OperationCompleted;
use CoreX\Observability\Data\OperationContext;
use CoreX\Observability\Data\OperationFailed;
use CoreX\Observability\Data\OperationStarted;
use CoreX\Observability\Enums\OperationOutcome;
use CoreX\Observability\Internal\NullOperationObserver;
use CoreX\Observability\Internal\PsrLogOperationObserver;
use Psr\Log\AbstractLogger;

it('keeps one context through start and complete', function (): void {
    $context = operationContext();
    $started = new OperationStarted(context: $context, occurredAt: new DateTimeImmutable('2026-09-25T03:00:00Z'));
    $completed = new OperationCompleted(
        context: $context,
        durationMilliseconds: 15,
        outcome: OperationOutcome::Succeeded,
    );

    expect($started->context)->toBe($completed->context)
        ->and($completed->outcome)->toBe(OperationOutcome::Succeeded)
        ->and($completed->durationMilliseconds)->toBe(15);
});

it('keeps one context through start and fail', function (): void {
    $context = operationContext();
    $started = new OperationStarted(context: $context, occurredAt: new DateTimeImmutable('2026-09-25T03:00:00Z'));
    $failed = new OperationFailed(
        context: $context,
        durationMilliseconds: 4,
        error: new ErrorDescriptor(code: CoreErrorCode::StorageOperationFailed, category: ErrorCategory::Unavailable, retryable: true),
    );

    expect($started->context)->toBe($failed->context)
        ->and($failed->error->code)->toBe('core.storage_operation_failed')
        ->and($failed->durationMilliseconds)->toBe(4);
});

it('rejects invalid identifiers, negative attempt and unknown outcome', function (): void {
    expect(fn () => operationContext(operationId: ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => operationContext(operationId: str_repeat('a', 129)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => operationContext(correlationId: "bad\nid"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => operationContext(package: 'Core'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => operationContext(operation: 'Publish'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => operationContext(attempt: -1))->toThrow(InvalidArgumentException::class, 'Attempt cannot be negative.')
        ->and(fn () => new OperationCompleted(
            context: operationContext(),
            durationMilliseconds: -1,
            outcome: OperationOutcome::Cancelled,
        ))->toThrow(InvalidArgumentException::class, 'Duration cannot be negative.')
        ->and(fn () => OperationOutcome::from('exploded'))->toThrow(ValueError::class);
});

it('logs exact static messages and keys', function (): void {
    $logger = new OperationMemoryLogger;
    $observer = new PsrLogOperationObserver(logger: $logger);
    $context = operationContext();
    $occurredAt = new DateTimeImmutable('2026-09-25T03:00:00+00:00');

    $observer->started(event: new OperationStarted(context: $context, occurredAt: $occurredAt));
    $observer->completed(event: new OperationCompleted(
        context: $context,
        durationMilliseconds: 8,
        outcome: OperationOutcome::Skipped,
    ));
    $observer->failed(event: new OperationFailed(
        context: $context,
        durationMilliseconds: 8,
        error: new ErrorDescriptor(
            code: CoreErrorCode::DepartmentCycleException,
            category: ErrorCategory::Conflict,
            retryable: false,
            path: '/field/name',
        ),
    ));

    expect($logger->records)->toHaveCount(3)
        ->and($logger->records[0]['message'])->toBe('corex.operation.started')
        ->and($logger->records[0]['context'])->toMatchArray([
            'operation_id' => 'op-1',
            'correlation_id' => 'corr-1',
            'causation_id' => 'cause-1',
            'account_id' => 'account-1',
            'workspace_id' => 'workspace-1',
            'actor_type' => 'user',
            'actor_id' => 'actor-1',
            'package' => 'core.messaging',
            'operation' => 'outbox.publish',
            'attempt' => 2,
            'occurred_at' => $occurredAt->format(DateTimeInterface::ATOM),
        ])
        ->and($logger->records[1]['message'])->toBe('corex.operation.completed')
        ->and($logger->records[1]['context'])->toMatchArray([
            'duration_ms' => 8,
            'outcome' => 'skipped',
        ])
        ->and($logger->records[2]['message'])->toBe('corex.operation.failed')
        ->and(array_keys($logger->records[2]['context']))->toBe([
            'operation_id',
            'correlation_id',
            'causation_id',
            'account_id',
            'workspace_id',
            'actor_type',
            'actor_id',
            'package',
            'operation',
            'attempt',
            'duration_ms',
            'code',
            'category',
            'path',
            'retryable',
        ]);
});

it('omits canary secrets from failure logs', function (): void {
    $logger = new OperationMemoryLogger;
    $observer = new PsrLogOperationObserver(logger: $logger);
    $canary = 'canary-secret-xyz';

    $observer->failed(event: new OperationFailed(
        context: operationContext(),
        durationMilliseconds: 1,
        error: new ErrorDescriptor(
            code: CoreErrorCode::InternalError,
            category: ErrorCategory::Internal,
            retryable: false,
            path: '/job',
            details: ['message' => $canary, 'token' => $canary],
        ),
    ));

    expect(json_encode($logger->records, JSON_THROW_ON_ERROR))->not->toContain($canary)
        ->and($logger->records[0]['context'])->not->toHaveKey('details')
        ->and($logger->records[0]['context'])->not->toHaveKey('exception');
});

it('does not throw from the null observer or a failing logger', function (): void {
    $context = operationContext();
    $started = new OperationStarted(context: $context, occurredAt: new DateTimeImmutable('2026-09-25T03:00:00Z'));
    $completed = new OperationCompleted(context: $context, durationMilliseconds: 0, outcome: OperationOutcome::Succeeded);
    $failed = new OperationFailed(
        context: $context,
        durationMilliseconds: 0,
        error: new ErrorDescriptor(code: CoreErrorCode::InternalError, category: ErrorCategory::Internal),
    );

    $null = new NullOperationObserver;
    $null->started(event: $started);
    $null->completed(event: $completed);
    $null->failed(event: $failed);

    $observer = new PsrLogOperationObserver(logger: new OperationThrowingLogger);

    expect(fn () => $observer->failed(event: $failed))->not->toThrow(Throwable::class);
});

function operationContext(
    string $operationId = 'op-1',
    string $correlationId = 'corr-1',
    string $package = 'core.messaging',
    string $operation = 'outbox.publish',
    int $attempt = 2,
): OperationContext {
    return new OperationContext(
        operationId: $operationId,
        correlationId: $correlationId,
        causationId: 'cause-1',
        accountId: 'account-1',
        workspaceId: 'workspace-1',
        actorType: ActorType::User,
        actorId: 'actor-1',
        package: $package,
        operation: $operation,
        attempt: $attempt,
    );
}

final class OperationMemoryLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class OperationThrowingLogger extends AbstractLogger
{
    public function log($level, string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('logger down');
    }
}
