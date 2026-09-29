<?php

declare(strict_types=1);

namespace CoreX\Observability\Internal;

use CoreX\Observability\Contracts\OperationObserver;
use CoreX\Observability\Data\OperationCompleted;
use CoreX\Observability\Data\OperationFailed;
use CoreX\Observability\Data\OperationStarted;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Projects operation events into PSR-3 context.
 *
 * Account, workspace, actor, correlation, causation and operation ids stay in
 * the log context. They are forbidden as metric labels.
 */
final class PsrLogOperationObserver implements OperationObserver
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function started(OperationStarted $event): void
    {
        $this->write(
            level: LogLevel::INFO,
            message: 'corex.operation.started',
            context: [
                ...$this->context($event->context),
                'occurred_at' => $event->occurredAt->format(DateTimeInterface::ATOM),
            ],
        );
    }

    public function completed(OperationCompleted $event): void
    {
        $this->write(
            level: LogLevel::INFO,
            message: 'corex.operation.completed',
            context: [
                ...$this->context($event->context),
                'duration_ms' => $event->durationMilliseconds,
                'outcome' => $event->outcome->value,
            ],
        );
    }

    public function failed(OperationFailed $event): void
    {
        $this->write(
            level: LogLevel::ERROR,
            message: 'corex.operation.failed',
            context: [
                ...$this->context($event->context),
                'duration_ms' => $event->durationMilliseconds,
                'code' => $event->error->code,
                'category' => $event->error->category->value,
                'path' => $event->error->path,
                'retryable' => $event->error->retryable,
            ],
        );
    }

    /**
     * Account, workspace, actor and operation ids are log fields only.
     *
     * @return array{
     *     operation_id: string,
     *     correlation_id: string,
     *     causation_id: ?string,
     *     account_id: ?string,
     *     workspace_id: ?string,
     *     actor_type: string,
     *     actor_id: ?string,
     *     package: string,
     *     operation: string,
     *     attempt: int
     * }
     */
    private function context(OperationContext $context): array
    {
        return [
            'operation_id' => $context->operationId,
            'correlation_id' => $context->correlationId,
            'causation_id' => $context->causationId,
            'account_id' => $context->accountId,
            'workspace_id' => $context->workspaceId,
            'actor_type' => $context->actorType->value,
            'actor_id' => $context->actorId,
            'package' => $context->package,
            'operation' => $context->operation,
            'attempt' => $context->attempt,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log(level: $level, message: $message, context: $context);
        } catch (Throwable) {
            // Adapter failure must not change the observed business outcome.
        }
    }
}
