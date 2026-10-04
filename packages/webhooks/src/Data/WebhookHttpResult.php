<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Attributes\Api;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class WebhookHttpResult
{
    public function __construct(
        public ?int $status,
        public ?string $retryAfter,
        public ?WebhookFailureReason $errorCode,
        public int $durationMs,
    ) {
        if ($status !== null && ($status < 100 || $status > 599)) {
            throw new InvalidArgumentException('Webhook HTTP status is invalid.');
        }

        if ($retryAfter !== null
            && ($retryAfter === ''
                || strlen($retryAfter) > 255
                || preg_match('/[\x00-\x1f\x7f]/', $retryAfter) === 1)) {
            throw new InvalidArgumentException('Webhook Retry-After value is invalid.');
        }

        if ($status === null && $errorCode === null) {
            throw new InvalidArgumentException('Webhook result must contain a status or a safe error code.');
        }

        if ($durationMs < 0) {
            throw new InvalidArgumentException('Webhook duration must be non-negative.');
        }
    }
}
