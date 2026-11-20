<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use CoreX\Notifications\Delivery\Enums\DeliveryOutcomeKind;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DeliveryOutcome
{
    public function __construct(public DeliveryOutcomeKind $kind, public ?string $providerRef, public ?int $retryAfter, public string $reason, public bool $absenceProven = false)
    {
        if (($kind === DeliveryOutcomeKind::Accepted && $providerRef === null)
            || ($providerRef !== null && ($providerRef === '' || strlen($providerRef) > 255 || preg_match('/[\x00-\x1f\x7f]/', $providerRef) === 1))
            || ($kind === DeliveryOutcomeKind::Retryable && ($retryAfter === null || $retryAfter < 1 || $retryAfter > 300))
            || ($kind !== DeliveryOutcomeKind::Retryable && $retryAfter !== null)
            || ($absenceProven && $kind !== DeliveryOutcomeKind::Retryable)
            || preg_match('/\A[a-z][a-z0-9._-]{0,95}\z/D', $reason) !== 1) {
            throw new InvalidArgumentException('Invalid notification delivery outcome.');
        }
    }
}
