<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use Closure;
use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use DateInterval;
use DateTimeImmutable;
use Packman\Attributes\Internal;
use Throwable;

/** @internal Pure finite retry policy; no durable state is changed here. */
#[Internal]
final readonly class WebhookRetryPolicy
{
    private const int MaxRetryAfterSeconds = 86_400;

    /** @var Closure(int, int): int */
    private Closure $random;

    /** @param null|callable(int, int): int $random */
    public function __construct(private WebhookOptions $options, ?callable $random = null)
    {
        $this->random = $random === null
            ? random_int(...)
            : Closure::fromCallable($random);
    }

    /**
     * @return array{status: 'succeeded'|'retry'|'dead', next: DateTimeImmutable, error: WebhookFailureReason|null}
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

        if (($failure = $this->budgetFailure(delivery: $delivery, instant: $now)) !== null) {
            return ['status' => 'dead', 'next' => $now, 'error' => $failure];
        }

        $cap = min(3_600, 5 * (2 ** min(10, max(0, $delivery->attempt - 1))));
        $jitter = max(1, ($this->random)(0, $cap));
        $retryAfter = $this->retryAfterSeconds(value: $result->retryAfter, now: $now);
        $delay = max($jitter, $retryAfter ?? 0);
        $next = $now->add(new DateInterval("PT{$delay}S"));

        if (($failure = $this->budgetFailure(delivery: $delivery, instant: $next)) !== null) {
            return ['status' => 'dead', 'next' => $now, 'error' => $failure];
        }

        return ['status' => 'retry', 'next' => $next, 'error' => $classification['error']];
    }

    public function budgetFailure(WebhookDelivery $delivery, DateTimeImmutable $instant): ?WebhookFailureReason
    {
        if ($delivery->attempt >= $this->options->maxAttempts) {
            return WebhookFailureReason::RetryExhausted;
        }

        $horizon = $delivery->createdAt->add(new DateInterval('PT'.$this->options->retryHorizonSeconds.'S'));

        return $instant >= $horizon ? WebhookFailureReason::RetryHorizonExhausted : null;
    }

    /** @return array{status: 'succeeded'|'retry'|'dead', error: WebhookFailureReason|null} */
    private function classification(WebhookHttpResult $result): array
    {
        // A rejected transport response is not a trustworthy HTTP acknowledgement.
        if ($result->errorCode?->isTerminal() === true) {
            return ['status' => 'dead', 'error' => $result->errorCode];
        }

        if ($result->status !== null && $result->status >= 200 && $result->status < 300) {
            return ['status' => 'succeeded', 'error' => null];
        }

        $error = ($result->status !== null ? WebhookFailureReason::fromHttpStatus($result->status) : null)
            ?? $result->errorCode
            ?? WebhookFailureReason::TransportFailure;

        return ['status' => $error->isTerminal() ? 'dead' : 'retry', 'error' => $error];
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
