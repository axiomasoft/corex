<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Laravel\WebhookOptionsParser;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class WebhookRetryBudgetTest extends TestCase
{
    use WebhookDeliveryFixture;

    /** @return iterable<string, array{int, int, int, string, string}> */
    public static function budgets(): iterable
    {
        yield 'attempt limit' => [3, 3, 10, 'dead', 'retry_exhausted'];
        yield 'raised attempt limit' => [20, 12, 10, 'retry', 'ack_unknown'];
        yield 'just before horizon' => [20, 1, 3599, 'retry', 'ack_unknown'];
        yield 'at horizon' => [20, 1, 3600, 'dead', 'retry_horizon_exhausted'];
        yield 'after horizon' => [20, 1, 7200, 'dead', 'retry_horizon_exhausted'];
        yield 'both limits' => [3, 3, 7200, 'dead', 'retry_exhausted'];
    }

    #[DataProvider('budgets')]
    public function test_recovery_uses_options_and_keeps_unknown_ack(int $max, int $attempt, int $age, string $status, string $error): void
    {
        $now = $this->clock->now();
        $this->seedDelivery('delivery', 'subscription', $attempt - 1, $now->sub(new DateInterval('PT'.$age.'S')));
        $store = $this->budgetStore($max);
        $claim = $store->claim($this->scope, 'lost-worker');
        self::assertNotNull($claim);
        $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery')->update(['lease_until' => $now, 'next_attempt_at' => $now->modify('+1 day')]);
        $this->seedDelivery('next-ready', 'next-subscription');
        $this->connection->table('sys_api_webhook_deliveries')->where('id', 'next-ready')->update(['next_attempt_at' => $now->modify('-1 second')]);
        $store->claim($this->scope, 'recovery-worker');
        $row = $store->find($this->scope, 'delivery');
        self::assertNotNull($row);
        $record = $this->connection->table('sys_api_webhook_attempts')->where('attempt', $attempt)->first();
        self::assertSame('unknown', $record?->outcome);
        self::assertSame('ack_unknown', $record?->error_code);
        self::assertSame($status, $row->status);
        self::assertSame($error, $row->lastError);
        self::assertSame($attempt, $row->attempt);
        self::assertFalse($store->finish($claim, new WebhookHttpResult(204, null, null, 1)));
    }

    #[DataProvider('budgets')]
    public function test_normal_retry_uses_same_budget(int $max, int $attempt, int $age, string $status, string $error): void
    {
        $now = $this->clock->now();
        $this->seedDelivery('delivery', 'subscription', $attempt - 1, $now->sub(new DateInterval('PT'.$age.'S')));
        $store = $this->budgetStore($max);
        $claim = $store->claim($this->scope, 'worker');
        self::assertNotNull($claim);
        self::assertTrue($store->finish($claim, new WebhookHttpResult(null, null, WebhookFailureReason::NetworkFailure, 1)));
        $row = $store->find($this->scope, 'delivery');
        self::assertNotNull($row);
        // A next attempt exactly at the horizon is already exhausted.
        $atNextBoundary = $age === 3599;
        self::assertSame($atNextBoundary ? 'dead' : $status, $row->status);
        $expected = $atNextBoundary ? 'retry_horizon_exhausted' : ($status === 'retry' ? 'network_failure' : $error);
        self::assertSame($expected, $row->lastError);
        self::assertSame($expected, $this->connection->table('sys_api_webhook_attempts')->value('error_code'));
    }

    public function test_success_and_permanent_http_failure_preserve_classification_when_budget_is_exhausted(): void
    {
        foreach ([204 => ['succeeded', null], 410 => ['dead', 'endpoint_gone']] as $http => [$status, $error]) {
            $this->seedDelivery('delivery-'.$http, 'subscription-'.$http, 2, $this->clock->now()->modify('-2 hours'));
            $store = $this->budgetStore(3);
            $claim = $store->claim($this->scope, 'worker');
            self::assertNotNull($claim);
            self::assertTrue($store->finish($claim, new WebhookHttpResult($http, null, null, 1)));
            $finished = $store->find($this->scope, $claim->id);
            self::assertSame($status, $finished?->status);
            self::assertSame($error, $finished?->lastError);
        }
        self::assertSame('disabled', $this->connection->table('sys_api_webhook_subscriptions')->where('id', 'subscription-410')->value('status'));
    }

    public function test_claim_rejection_persists_typed_endpoint_and_signing_reasons(): void
    {
        foreach (['endpoint' => 'webhook_endpoint_changed', 'keys' => 'signing_unavailable'] as $case => $reason) {
            $this->seedDelivery($case, 'subscription-'.$case);
            $store = $this->budgetStore(20);
            $claim = $store->claim($this->scope, 'worker');
            self::assertNotNull($claim);
            $this->connection->table('sys_api_webhook_subscriptions')->where('id', $claim->subscriptionId)->update($case === 'endpoint' ? ['revision' => 1] : ['key_refs' => '[]']);
            self::assertNull($store->signingKeyRefs($this->scope, $claim));
            self::assertSame('dead', $store->find($this->scope, $claim->id)?->status);
            self::assertSame($reason, $store->find($this->scope, $claim->id)?->lastError);
            self::assertSame($reason, $this->connection->table('sys_api_webhook_attempts')->where('delivery_id', $claim->id)->value('error_code'));
        }
    }

    public function test_transport_rejections_override_untrusted_http_status_without_disabling_subscription(): void
    {
        foreach ([200, 410, 429, 503] as $http) {
            foreach ([WebhookFailureReason::AddressNotAllowed, WebhookFailureReason::PayloadTooLarge] as $reason) {
                $id = $http.'-'.$reason->name;
                $this->seedDelivery($id, 'subscription-'.$id);
                $store = $this->budgetStore(20);
                $claim = $store->claim($this->scope, 'worker');
                self::assertNotNull($claim);
                self::assertTrue($store->finish($claim, new WebhookHttpResult($http, null, $reason, 1)));
                self::assertSame('dead', $store->find($this->scope, $id)?->status);
                self::assertSame($reason->value, $store->find($this->scope, $id)?->lastError);
                self::assertSame('active', $this->connection->table('sys_api_webhook_subscriptions')->where('id', 'subscription-'.$id)->value('status'));
            }
        }
    }

    public function test_options_reject_invalid_attempt_and_horizon_ranges(): void
    {
        foreach ([['max_attempts' => 0], ['max_attempts' => 21], ['max_attempts' => '3'], ['retry_horizon_seconds' => 3599], ['retry_horizon_seconds' => 604801], ['retry_horizon_seconds' => '3600'], ['body_retention_seconds' => 3600, 'replay_horizon_seconds' => 3600, 'retry_horizon_seconds' => 3601]] as $config) {
            try {
                WebhookOptionsParser::fromArray($config);
                self::fail('Invalid retry budget accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('Invalid configuration', $exception->getMessage());
            }
        }
        self::assertSame(1, WebhookOptionsParser::fromArray(['max_attempts' => 1])->maxAttempts);
        self::assertSame(604800, WebhookOptionsParser::fromArray(['retry_horizon_seconds' => 604800])->retryHorizonSeconds);
    }

    private function budgetStore(int $max): SqlWebhookDeliveryStore
    {
        return new SqlWebhookDeliveryStore($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->clock, new WebhookRetryPolicy(WebhookOptionsParser::fromArray(['max_attempts' => $max, 'retry_horizon_seconds' => 3600]), static fn (): int => 1));
    }
}
