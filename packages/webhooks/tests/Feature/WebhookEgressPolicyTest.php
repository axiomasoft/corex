<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Internal\PermitPublicEgressPolicy;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use CoreX\Webhooks\Laravel\WebhookBindingsRegistrar;
use CoreX\Webhooks\Laravel\WebhookOptionsParser;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use RuntimeException;

final class WebhookEgressPolicyTest extends TestCase
{
    use WebhookDeliveryFixture;

    public function test_create_and_update_are_denied_before_any_write(): void
    {
        $policy = new RevocableEgressPolicy;
        $store = $this->subscriptions($policy);
        $subscription = $this->subscription();
        $policy->allowed = false;
        $this->assertDenied(fn () => $store->create($this->scope, $subscription, true, true));
        self::assertSame(0, $this->connection->table('sys_api_webhook_subscriptions')->count());
        $policy->allowed = true;
        $store->create($this->scope, $subscription, true, true);
        $policy->allowed = false;
        $this->assertDenied(fn () => $store->update($this->scope, 'subscription', 0, 'https://other.example/receiver', ['current'], true, true));
        $row = $store->find($this->scope, 'subscription');
        self::assertSame(0, $row?->revision);
        self::assertSame('https://hooks.example/receiver', $row?->endpointUrl);
        self::assertSame('other.example', $policy->destinations[2]->host);
        self::assertSame($this->scope, $policy->scopes[2]);
    }

    public function test_revocation_stops_delivery_before_dns_and_transport_including_replay(): void
    {
        $policy = new RevocableEgressPolicy;
        $this->subscriptions($policy)->create($this->scope, $this->subscription(), true, true);
        $this->seedDelivery('delivery', 'seed-subscription');
        $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery')->update(['subscription_id' => 'subscription']);
        // A recorded actor proves the delivery's persisted scope is passed to the SPI.
        $recorded = new DataScope('account-1', null, 'sqlite::memory:', 'recorded-actor', $this->scope->restoreEpoch);
        $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery')->update(['scope' => json_encode(resolve(DataScopeCodec::class)->encode($recorded), JSON_THROW_ON_ERROR)]);
        $policy->allowed = false;
        $dns = 0;
        $transport = new EgressRecordingTransport;
        $worker = new WebhookDeliveryWorker(
            store: new SqlWebhookDeliveryStore($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->clock, new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]))),
            signer: $this->signer(),
            urlPolicy: new WebhookUrlPolicy(static function () use (&$dns): array {
                $dns++;

                return ['93.184.216.34'];
            }),
            transport: $transport,
            topics: $this->topics(),
            clock: $this->clock,
            egress: $policy,
            scopeCodec: resolve(DataScopeCodec::class),
        );
        $first = $worker->workOnce($this->scope, 'worker');
        self::assertSame('dead', $first?->status);
        self::assertSame('egress_denied', $first?->lastError);
        self::assertSame(0, $dns);
        self::assertSame(0, $transport->sends);
        self::assertNull($policy->scopes[1]->workspaceId);
        self::assertSame('recorded-actor', $policy->scopes[1]->actorId);
        self::assertSame('egress_denied', $this->connection->table('sys_api_webhook_attempts')->where('delivery_id', 'delivery')->value('error_code'));

        $replay = new WebhookReplay($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->topics(), $this->clock, resolve(CursorCodec::class), $audit = new EgressAuditLogger);
        $replay->one($recorded, 'delivery', false, null);
        self::assertSame('pending', $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery')->value('status'));
        $second = $worker->workOnce($this->scope, 'replay-worker');
        self::assertSame('dead', $second?->status);
        self::assertSame('egress_denied', $second?->lastError);
        self::assertSame(2, $second?->attempt);
        self::assertSame(1, $second?->replayCount);
        self::assertCount(1, $audit->entries);
        self::assertSame(0, $dns);
        self::assertSame(0, $transport->sends);
    }

    public function test_revocation_is_checked_again_on_an_ordinary_retry(): void
    {
        $policy = new RevocableEgressPolicy;
        $this->seedDelivery('delivery', 'subscription');
        $transport = new EgressRecordingTransport;
        $transport->result = new WebhookHttpResult(503, null, null, 1);
        $worker = new WebhookDeliveryWorker(new SqlWebhookDeliveryStore($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->clock, new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]), static fn (): int => 1)), $this->signer(), $this->urlPolicy(), $transport, $this->topics(), $this->clock, $policy, resolve(DataScopeCodec::class));
        self::assertSame('retry', $worker->workOnce($this->scope, 'worker')?->status);
        $policy->allowed = false;
        $this->clock->advance(new DateInterval('PT2S'));
        self::assertSame('egress_denied', $worker->workOnce($this->scope, 'worker')?->lastError);
        self::assertSame(1, $transport->sends);
    }

    public function test_denial_persistence_failure_is_not_reclassified_as_dns_retry(): void
    {
        $this->seedDelivery('delivery', 'subscription');
        $policy = new RevocableEgressPolicy;
        $policy->allowed = false;
        $this->connection->beforeExecuting(static function (string $query, array $bindings): void {
            if (str_starts_with($query, 'update') && in_array('egress_denied', $bindings, true)) {
                throw new RuntimeException('simulated persistence failure');
            }
        });
        $transport = new EgressRecordingTransport;
        $worker = new WebhookDeliveryWorker(new SqlWebhookDeliveryStore($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->clock, new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]))), $this->signer(), $this->urlPolicy(), $transport, $this->topics(), $this->clock, $policy, resolve(DataScopeCodec::class));

        try {
            $worker->workOnce($this->scope, 'worker');
            self::fail('Persistence failure was hidden.');
        } catch (RuntimeException $error) {
            self::assertSame('simulated persistence failure', $error->getMessage());
        }
        self::assertSame('sending', $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery')->value('status'));
        self::assertNull($this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery')->value('last_error'));
        self::assertSame('pending', $this->connection->table('sys_api_webhook_attempts')->where('delivery_id', 'delivery')->value('outcome'));
        self::assertSame(0, $transport->sends);
    }

    public function test_spi_failure_stops_before_dns_and_is_not_reclassified(): void
    {
        $this->seedDelivery('delivery', 'subscription');
        $policy = new RevocableEgressPolicy;
        $policy->failure = new RuntimeException('policy unavailable');
        $transport = new EgressRecordingTransport;
        $dns = 0;
        $worker = new WebhookDeliveryWorker(new SqlWebhookDeliveryStore($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->clock, new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]))), $this->signer(), new WebhookUrlPolicy(static function () use (&$dns): array {
            $dns++;

            return ['93.184.216.34'];
        }), $transport, $this->topics(), $this->clock, $policy, resolve(DataScopeCodec::class));

        try {
            $worker->workOnce($this->scope, 'worker');
            self::fail('SPI failure was hidden.');
        } catch (RuntimeException $error) {
            self::assertSame('policy unavailable', $error->getMessage());
        }
        self::assertSame(0, $dns);
        self::assertSame(0, $transport->sends);
        self::assertSame('pending', $this->connection->table('sys_api_webhook_attempts')->where('delivery_id', 'delivery')->value('outcome'));
    }

    public function test_default_binding_is_permissive_and_host_binding_is_preserved(): void
    {
        self::assertInstanceOf(PermitPublicEgressPolicy::class, resolve(WebhookEgressPolicy::class));
        self::assertTrue(resolve(WebhookEgressPolicy::class)->allows($this->scope, WebhookDestination::fromUrl('HTTPS://HOOKS.EXAMPLE:443')));
        $policy = new RevocableEgressPolicy;
        $this->app->instance(WebhookEgressPolicy::class, $policy);
        (new WebhookBindingsRegistrar)->register($this->app);
        self::assertSame($policy, resolve(WebhookEgressPolicy::class));
    }

    private function subscriptions(RevocableEgressPolicy $policy): SqlWebhookSubscriptionStore
    {
        return new SqlWebhookSubscriptionStore($this->connection, new GuardedTenantContextRestorer($this->contexts), $this->topics(), $this->clock, $policy);
    }

    private function subscription(): WebhookSubscription
    {
        return new WebhookSubscription('subscription', 0, 'v1', 'orders.placed', 1, 'https://hooks.example/receiver', 'active', 'personal', ['current'], $this->clock->now(), $this->clock->now());
    }

    private function assertDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Egress restriction was ignored.');
        } catch (ApiError $error) {
            self::assertSame(422, $error->status);
            self::assertSame('webhooks.webhook_egress_denied', $error->errorCode);
        }
    }
}

final class RevocableEgressPolicy implements WebhookEgressPolicy
{
    public bool $allowed = true;

    public ?RuntimeException $failure = null;

    /** @var list<WebhookDestination> */
    public array $destinations = [];

    /** @var list<DataScope> */
    public array $scopes = [];

    public function allows(DataScope $scope, WebhookDestination $destination): bool
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->destinations[] = $destination;
        $this->scopes[] = $scope;

        return $this->allowed;
    }
}

final class EgressRecordingTransport implements WebhookHttpTransport
{
    public int $sends = 0;

    public ?WebhookHttpResult $result = null;

    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        $this->sends++;

        return $this->result ?? new WebhookHttpResult(204, null, null, 1);
    }
}

final class EgressAuditLogger implements AuditLogger
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function record(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}
