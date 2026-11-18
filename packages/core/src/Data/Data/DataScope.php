<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Contracts\DurablePayload;
use InvalidArgumentException;
use Packman\Attributes\Api;

/**
 * Область данных, пересекающая durable-границу: очередь, wakeup-запрос, чекпойнт
 * переноса. Состоит только из скаляров; живой TenantContext в неё не входит и
 * восстанавливается отдельно через TenantContextRestorer (RUN-002, RUN-003).
 */
#[Api(since: '1.0')]
final readonly class DataScope implements DurablePayload
{
    public const int VERSION = 2;

    public function __construct(
        public string $accountId,
        public ?string $workspaceId,
        public string $connectionIdentity,
        public string $actorId,
        public string $restoreEpoch,
        public bool $boxed = false,
        public int $version = self::VERSION,
    ) {
        self::assertOpaque(value: $accountId, name: 'account identity');

        if ($workspaceId !== null) {
            self::assertOpaque(value: $workspaceId, name: 'workspace identity');
        }

        self::assertOpaque(value: $connectionIdentity, name: 'connection identity');
        self::assertOpaque(value: $actorId, name: 'actor identity');

        if (preg_match('/\\A[a-f0-9]{64}\\z/D', $restoreEpoch) !== 1) {
            throw new InvalidArgumentException('Restore epoch must be a lowercase SHA-256 digest.');
        }

        if ($version !== self::VERSION) {
            throw new InvalidArgumentException("Unsupported data scope version [{$version}].");
        }
    }

    /**
     * Единственная точка построения скоупа из живого рантайм-контекста.
     */
    public static function fromTenantContext(
        TenantContext $context,
        string $connectionIdentity,
        string $actorId,
        string $restoreEpoch,
    ): self {
        return new self(
            accountId: $context->account->id,
            workspaceId: $context->workspace?->id,
            connectionIdentity: $connectionIdentity,
            actorId: $actorId,
            restoreEpoch: $restoreEpoch,
            boxed: $context->isBoxed(),
        );
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\\x00-\\x1f\\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
    }
}
