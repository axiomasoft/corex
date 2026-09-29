<?php

declare(strict_types=1);

namespace CoreX\Observability\Internal;

use CoreX\Audit\Enums\ActorType;
use InvalidArgumentException;

/**
 * Immutable identity of one long-running operation.
 *
 * Account, workspace, actor, correlation, causation and operation ids are
 * structured log and trace fields. They are forbidden as metric labels.
 */
final readonly class OperationContext
{
    public function __construct(
        public string $operationId,
        public string $correlationId,
        public ?string $causationId,
        public ?string $accountId,
        public ?string $workspaceId,
        public ActorType $actorType,
        public ?string $actorId,
        public string $package,
        public string $operation,
        public int $attempt,
    ) {
        self::assertOpaque(value: $operationId, name: 'operation id');
        self::assertOpaque(value: $correlationId, name: 'correlation id');
        self::assertOptionalOpaque(value: $causationId, name: 'causation id');
        self::assertOptionalOpaque(value: $accountId, name: 'account id');
        self::assertOptionalOpaque(value: $workspaceId, name: 'workspace id');
        self::assertOptionalOpaque(value: $actorId, name: 'actor id');
        self::assertDottedName(value: $package, name: 'package');
        self::assertDottedName(value: $operation, name: 'operation');

        if ($attempt < 0) {
            throw new InvalidArgumentException('Attempt cannot be negative.');
        }
    }

    private static function assertOptionalOpaque(?string $value, string $name): void
    {
        if ($value !== null) {
            self::assertOpaque(value: $value, name: $name);
        }
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\\x00-\\x1f\\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
    }

    private static function assertDottedName(string $value, string $name): void
    {
        if (preg_match('/\\A[a-z][a-z0-9]*(?:\\.[a-z0-9]+)*\\z/D', $value) !== 1 || strlen($value) > 128) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
    }
}
