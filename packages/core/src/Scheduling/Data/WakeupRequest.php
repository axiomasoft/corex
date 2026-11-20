<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class WakeupRequest
{
    /** Immutable JSON value. */
    public function __construct(
        public DataScope $scope,
        public string $kind,
        public string $handlerVersion,
        public string $requestKey,
        public DateTimeImmutable $fireAt,
        public ?WakeupWindow $window,
        public JsonDocument $payload,
    ) {
        new WakeupIdentity($kind, $requestKey);

        if (
            $handlerVersion === '' || trim($handlerVersion) !== $handlerVersion || strlen($handlerVersion) > 128
            || $fireAt->getOffset() !== 0
            || ($window !== null && $fireAt < $window->end)
        ) {
            throw new InvalidArgumentException('Invalid wakeup request.');
        }
    }
}
