<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Laravel\WebhookOptionsParser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookRetryPolicyBoundaryTest extends TestCase
{
    #[DataProvider('jitterCaps')]
    public function test_exponential_jitter_is_bounded_and_zero_jitter_still_waits_one_second(int $attempt, int $cap): void
    {
        $now = new DateTimeImmutable('2030-01-01T00:00:00Z');
        $policy = new WebhookRetryPolicy(options: WebhookOptionsParser::fromArray(config: ['max_attempts' => 20]), random: function (int $minimum, int $maximum) use ($cap): int {
            self::assertSame(0, $minimum);
            self::assertSame($cap, $maximum);

            return 0;
        });
        $decision = $policy->decide(delivery: $this->delivery($attempt, $now), result: new WebhookHttpResult(status: 503, retryAfter: null, errorCode: null, durationMs: 0), now: $now);
        self::assertSame('retry', $decision['status']);
        self::assertSame(WebhookFailureReason::Http5xx, $decision['error']);
        self::assertSame($now->getTimestamp() + 1, $decision['next']->getTimestamp());
    }

    /** @return iterable<array{int, int}> */
    public static function jitterCaps(): iterable
    {
        yield [0, 5];
        yield [1, 5];
        yield [2, 10];
        yield [10, 2560];
        yield [11, 3600];
        yield [19, 3600];
    }

    #[DataProvider('retryAfterValues')]
    public function test_retry_after_seconds_and_dates_are_bounded_and_take_precedence_over_jitter(string $value, int $delay): void
    {
        $now = new DateTimeImmutable('2030-01-01T00:00:00Z');
        $policy = new WebhookRetryPolicy(options: WebhookOptionsParser::fromArray(config: []), random: static fn (): int => 1);
        $decision = $policy->decide(delivery: $this->delivery(1, $now), result: new WebhookHttpResult(status: 429, retryAfter: $value, errorCode: null, durationMs: 0), now: $now);
        self::assertSame('retry', $decision['status']);
        self::assertSame($now->getTimestamp() + $delay, $decision['next']->getTimestamp());
    }

    /** @return iterable<array{string, int}> */
    public static function retryAfterValues(): iterable
    {
        yield ['0', 1];
        yield ['1', 1];
        yield ['60', 60];
        yield ['86399', 86399];
        yield ['86400', 86400];
        yield ['86401', 86400];
        yield ['9999999999', 86400];
        yield ['Wed, 02 Jan 2030 00:00:00 GMT', 86400];
        yield ['Thu, 03 Jan 2030 00:00:00 GMT', 86400];
        yield ['Mon, 31 Dec 2029 00:00:00 GMT', 1];
        yield ['not-a-date', 1];
    }

    public function test_exhaustion_is_checked_at_both_now_and_the_scheduled_next_attempt(): void
    {
        $now = new DateTimeImmutable('2030-01-01T00:00:00Z');
        $options = WebhookOptionsParser::fromArray(config: ['max_attempts' => 3, 'retry_horizon_seconds' => 3600]);
        $policy = new WebhookRetryPolicy(options: $options, random: static fn (): int => 1);
        self::assertSame(WebhookFailureReason::RetryExhausted, $policy->budgetFailure(delivery: $this->delivery(3, $now), instant: $now));
        self::assertNull($policy->budgetFailure(delivery: $this->delivery(2, $now), instant: $now->modify('+3599 seconds')));
        self::assertSame(WebhookFailureReason::RetryHorizonExhausted, $policy->budgetFailure(delivery: $this->delivery(2, $now), instant: $now->modify('+3600 seconds')));
        $created = $now->modify('-3599 seconds');
        $decision = $policy->decide(delivery: $this->delivery(2, $created), result: new WebhookHttpResult(status: 503, retryAfter: null, errorCode: null, durationMs: 0), now: $now);
        self::assertSame(['status' => 'dead', 'next' => $now, 'error' => WebhookFailureReason::RetryHorizonExhausted], $decision);
        foreach ([204 => ['succeeded', null], 410 => ['dead', WebhookFailureReason::EndpointGone]] as $status => [$state, $error]) {
            self::assertSame(['status' => $state, 'next' => $now, 'error' => $error], $policy->decide(delivery: $this->delivery(3, $created), result: new WebhookHttpResult(status: $status, retryAfter: null, errorCode: null, durationMs: 0), now: $now));
        }
    }

    private function delivery(int $attempt, DateTimeImmutable $created): WebhookDelivery
    {
        return new WebhookDelivery(id: 'delivery', subscriptionId: 'subscription', subscriptionRevision: 0, eventId: 'event', topic: 'contact.created', topicVersion: 1, major: 'v1', endpointSnapshot: 'https://hooks.example.com/', scope: [], body: '{}', bodyHash: hash('sha256', '{}'), status: 'retry', attempt: $attempt, replayCount: 0, leaseOwner: null, leaseUntil: null, nextAttemptAt: $created, createdAt: $created, finishedAt: null, expiresAt: $created->modify('+7 days'), lastError: null);
    }
}
