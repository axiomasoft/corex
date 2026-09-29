<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

use CoreX\Context\Data\TenantContext;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class WakeupRequest
{
    /** @param array<array-key, mixed> $payload */
    public function __construct(
        public TenantContext $context,
        public string $kind,
        public string $requestKey,
        public DateTimeImmutable $fireAt,
        public ?WakeupWindow $window,
        public array $payload,
    ) {
        if (
            preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $kind) !== 1
            || $requestKey === ''
            || strlen($requestKey) > 128
            || preg_match('//u', $requestKey) !== 1
            || $fireAt->getOffset() !== 0
            || ($window !== null && $fireAt < $window->end)
        ) {
            throw new InvalidArgumentException('Invalid wakeup request.');
        }
    }
}
