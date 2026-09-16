<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

use CoreX\Tenancy\TenantContext;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class WakeupRequest
{
    /** @param array<string, mixed> $payload */
    public function __construct(public TenantContext $context, public string $kind, public string $requestKey, public DateTimeImmutable $fireAt, public ?WakeupWindow $window, public array $payload)
    {
        if ($kind === '' || strlen($kind) > 64 || $requestKey === '' || strlen($requestKey) > 255 || ($window !== null && $fireAt < $window->end)) {
            throw new InvalidArgumentException('Invalid wakeup request.');
        }
    }
}
