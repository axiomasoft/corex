<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Webhooks\Enums\WebhookFailureReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookFailureReasonTest extends TestCase
{
    /** @return iterable<string, array{int, ?WebhookFailureReason, ?bool}> */
    public static function statuses(): iterable
    {
        foreach ([200, 204, 299] as $status) {
            yield (string) $status => [$status, null, null];
        }
        foreach ([301, 307, 399] as $status) {
            yield (string) $status => [$status, WebhookFailureReason::RedirectForbidden, true];
        }
        foreach ([400, 401, 404, 499] as $status) {
            yield (string) $status => [$status, WebhookFailureReason::HttpPermanent, true];
        }
        yield '410' => [410, WebhookFailureReason::EndpointGone, true];
        yield '408' => [408, WebhookFailureReason::Http408, false];
        yield '425' => [425, WebhookFailureReason::Http425, false];
        yield '429' => [429, WebhookFailureReason::Http429, false];
        foreach ([500, 503, 599] as $status) {
            yield (string) $status => [$status, WebhookFailureReason::Http5xx, false];
        }
    }

    #[DataProvider('statuses')]
    public function test_http_classification(int $status, ?WebhookFailureReason $reason, ?bool $permanent): void
    {
        self::assertSame($reason, WebhookFailureReason::fromHttpStatus($status));
        self::assertSame($permanent, $reason?->isPermanent());
    }

    public function test_every_reason_round_trips_and_has_explicit_permanence_and_terminality(): void
    {
        $permanent = ['webhook_endpoint_changed', 'endpoint_gone', 'redirect_forbidden', 'http_permanent', 'url_forbidden', 'address_not_allowed', 'egress_denied', 'topic_unavailable', 'payload_too_large'];
        $budget = ['retry_exhausted', 'retry_horizon_exhausted'];
        self::assertCount(20, WebhookFailureReason::cases());
        foreach (WebhookFailureReason::cases() as $reason) {
            self::assertSame($reason, WebhookFailureReason::from($reason->value));
            self::assertSame(in_array($reason->value, $permanent, true), $reason->isPermanent(), $reason->name);
            self::assertSame(in_array($reason->value, [...$permanent, ...$budget], true), $reason->isTerminal(), $reason->name);
        }
    }
}
