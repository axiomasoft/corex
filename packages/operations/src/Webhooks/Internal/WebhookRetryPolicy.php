<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Webhook;

use Closure;
use CoreX\Api\Data\WebhookDelivery;
use CoreX\Api\Data\WebhookHttpResult;
use DateInterval;
use DateTimeImmutable;
use Throwable;

/** @internal Pure finite retry policy; no durable state is changed here. */
final readonly class WebhookRetryPolicy
{
    private const int MaxAttempts = 12;

    private const int MaxRetryAfterSeconds = 86_400;

    private const int HorizonSeconds = 72 * 60 * 60;

    /** @var Closure(int, int): int */
    private Closure $random;

    /** @param null|callable(int, int): int $random */
    public function __construct(?callable $random = null)
    {
        $this->random = $random === null
            ? static fn (int $min, int $max): int => random_int($min, $max)
            : Closure::fromCallable($random);
    }

    /**
     * @return array{status: 'succeeded'|'retry'|'dead', next: DateTimeImmutable, error: string|null}
     */
    public function decide(
        WebhookDelivery $delivery,
        WebhookHttpResult $result,
        DateTimeImmutable $now,
    ): array {
        $classification = $this->classification($result);

        if ($classification['status'] !== 'retry') {
            return [
                'status' => $classification['status'],
                'next' => $now,
                'error' => $classification['error'],
            ];
        }

        if ($delivery->attempt >= self::MaxAttempts) {
            return ['status' => 'dead', 'next' => $now, 'error' => 'retry_exhausted'];
        }

        $cap = min(3_600, 5 * (2 ** min(10, max(0, $delivery->attempt - 1))));
        $jitter = max(1, ($this->random)(0, $cap));
        $retryAfter = $this->retryAfterSeconds($result->retryAfter, $now);
        $delay = max($jitter, $retryAfter ?? 0);
        $next = $now->add(new DateInterval("PT{$delay}S"));
        $horizon = $delivery->createdAt->add(new DateInterval('PT'.self::HorizonSeconds.'S'));

        if ($next > $horizon) {
            return ['status' => 'dead', 'next' => $now, 'error' => 'retry_horizon_exhausted'];
        }

        return ['status' => 'retry', 'next' => $next, 'error' => $classification['error']];
    }

    /** @return array{status: 'succeeded'|'retry'|'dead', error: string|null} */
    private function classification(WebhookHttpResult $result): array
    {
        $status = $result->status;

        if ($status !== null && $status >= 200 && $status < 300) {
            return ['status' => 'succeeded', 'error' => null];
        }

        if ($status === 410) {
            return ['status' => 'dead', 'error' => 'endpoint_gone'];
        }

        if ($status !== null && $status >= 300 && $status < 400) {
            return ['status' => 'dead', 'error' => 'redirect_forbidden'];
        }

        if ($status !== null && $status >= 400 && $status < 500
            && ! in_array($status, [408, 425, 429], true)) {
            return ['status' => 'dead', 'error' => 'http_permanent'];
        }

        if ($status === 429) {
            return ['status' => 'retry', 'error' => 'http_429'];
        }

        if ($status !== null && $status >= 500) {
            return ['status' => 'retry', 'error' => 'http_5xx'];
        }

        if ($status !== null && in_array($status, [408, 425], true)) {
            return ['status' => 'retry', 'error' => 'http_'.$status];
        }

        $error = $result->errorCode ?? 'transport_failure';

        if (in_array($error, ['address_not_allowed', 'webhook_url_forbidden', 'topic_unavailable'], true)) {
            return ['status' => 'dead', 'error' => $error];
        }

        return ['status' => 'retry', 'error' => $error];
    }

    private function retryAfterSeconds(?string $value, DateTimeImmutable $now): ?int
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $value) === 1) {
            return min(self::MaxRetryAfterSeconds, (int) $value);
        }

        try {
            $instant = new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }

        return min(
            self::MaxRetryAfterSeconds,
            max(0, $instant->getTimestamp() - $now->getTimestamp()),
        );
    }
}
