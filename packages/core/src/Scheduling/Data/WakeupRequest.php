<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use CoreX\Attributes\Api;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use DateTimeImmutable;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class WakeupRequest
{
    /** Immutable JSON value. */
    public function __construct(
        public TenantContext $context,
        public string $kind,
        public string $requestKey,
        public DateTimeImmutable $fireAt,
        public ?WakeupWindow $window,
        public JsonDocument $payload,
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
