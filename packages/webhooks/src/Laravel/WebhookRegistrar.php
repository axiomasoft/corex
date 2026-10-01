<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Webhooks\Contracts\WebhookDeliveryStore;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Internal\CurlWebhookTransport;
use CoreX\Webhooks\Internal\HmacWebhookSigner;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookManagement;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookRetention;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use Illuminate\Contracts\Foundation\Application;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class WebhookRegistrar
{
    public static function register(Application $app): void
    {
        $config = $app['config']->get('corex-webhooks');
        $options = WebhookOptionsParser::fromArray(is_array($config) ? $config : []);
        $urlPolicy = new WebhookUrlPolicy(organizationDeniedCidrs: $options->organizationDeniedCidrs);
        $app->instance(WebhookOptions::class, $options);
        $app->instance(WebhookUrlPolicy::class, $urlPolicy);
        $app->singleton(WebhookRetryPolicy::class, WebhookRetryPolicy::class);
        $app->singleton(WebhookTopicCatalogue::class, static fn (Application $app): WebhookTopicCatalogue => new WebhookTopicCatalogue(
            contracts: $app->make(MessageContractRegistry::class),
            topics: $options->topics,
            projectors: self::projectors($app, $options),
        ));
        $app->singleton(WebhookSubscriptionStore::class, static fn (Application $app): SqlWebhookSubscriptionStore => new SqlWebhookSubscriptionStore(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            contexts: $app->make(TenantContextRestorer::class),
            catalogue: $app->make(WebhookTopicCatalogue::class),
            clock: $app->make(ClockInterface::class),
        ));
        $app->singleton(WebhookDeliveryStore::class, static fn (Application $app): SqlWebhookDeliveryStore => self::deliveryStore($app, $options));
        $app->singleton(WebhookManagement::class, static fn (Application $app): WebhookManagement => new WebhookManagement(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            contexts: $app->make(TenantContextRestorer::class),
            subscriptions: $app->make(WebhookSubscriptionStore::class),
            cursors: $app->make(CursorCodec::class),
        ));
        $app->singleton(WebhookReplay::class, static fn (Application $app): WebhookReplay => new WebhookReplay(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            contexts: $app->make(TenantContextRestorer::class),
            topics: $app->make(WebhookTopicCatalogue::class),
            clock: $app->make(ClockInterface::class),
            cursors: $app->make(CursorCodec::class),
            audit: $app->make(AuditLogger::class),
        ));
        $app->singleton(WebhookFanout::class, static fn (Application $app): WebhookFanout => new WebhookFanout(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            inbox: $app->make(Inbox::class),
            catalogue: $app->make(WebhookTopicCatalogue::class),
            projectors: self::projectors($app, $options),
            clock: $app->make(ClockInterface::class),
        ));
        $app->singleton(WebhookRetention::class, static fn (Application $app): WebhookRetention => new WebhookRetention(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            contexts: $app->make(TenantContextRestorer::class),
            clock: $app->make(ClockInterface::class),
            bodyRetentionSeconds: $options->bodyRetentionSeconds,
            historyRetentionSeconds: $options->historyRetentionSeconds,
            replayHorizonSeconds: $options->replayHorizonSeconds,
        ));
        $app->singleton(WebhookDeliveryWorker::class, static function (Application $app) use ($options): WebhookDeliveryWorker {
            self::assertDeliveryReady($app, $options);

            return new WebhookDeliveryWorker(
                store: self::deliveryStore($app, $options),
                signer: $app->make(WebhookSigner::class),
                urlPolicy: $app->make(WebhookUrlPolicy::class),
                transport: $app->make(WebhookHttpTransport::class),
                topics: $app->make(WebhookTopicCatalogue::class),
                clock: $app->make(ClockInterface::class),
            );
        });

        if (! $options->deliveryEnabled) {
            return;
        }

        $app->singleton(WebhookSigner::class, static function (Application $app): HmacWebhookSigner {
            if (! $app->bound(WebhookKeyRing::class)) {
                throw new RuntimeException('Webhook signing capability is unavailable: bind a host-owned WebhookKeyRing explicitly.');
            }

            return new HmacWebhookSigner($app->make(WebhookKeyRing::class));
        });
        $app->singleton(WebhookHttpTransport::class, static function (): CurlWebhookTransport {
            if (! function_exists('curl_init')) {
                throw new RuntimeException('Webhook delivery capability is unavailable: ext-curl is not installed.');
            }

            return new CurlWebhookTransport;
        });
    }

    public static function boot(Application $app): void
    {
        WebhookRouteRegistrar::register($app);
    }

    private static function deliveryStore(Application $app, WebhookOptions $options): SqlWebhookDeliveryStore
    {
        return new SqlWebhookDeliveryStore(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            contexts: $app->make(TenantContextRestorer::class),
            clock: $app->make(ClockInterface::class),
            retry: $app->make(WebhookRetryPolicy::class),
            leaseSeconds: $options->leaseSeconds,
        );
    }

    /** @return array<string, WebhookPayloadProjector> */
    private static function projectors(Application $app, WebhookOptions $options): array
    {
        $projectors = [];

        foreach ($options->projectors as $id => $service) {
            $projector = $app->make($service);

            if (! $projector instanceof WebhookPayloadProjector) {
                throw new RuntimeException("Webhook projector [{$id}] does not implement WebhookPayloadProjector.");
            }

            $projectors[$id] = $projector;
        }

        return $projectors;
    }

    private static function assertDeliveryReady(Application $app, WebhookOptions $options): void
    {
        if (! $options->deliveryEnabled) {
            throw new RuntimeException('Webhook remote delivery is disabled by policy.');
        }

        if (! $options->workerReady) {
            throw new RuntimeException('Webhook delivery worker readiness is not confirmed.');
        }

        if (! $app->bound(WebhookKeyRing::class)) {
            throw new RuntimeException('Webhook signing capability is unavailable: bind a host-owned WebhookKeyRing explicitly.');
        }

        if (! function_exists('curl_init')) {
            throw new RuntimeException('Webhook delivery capability is unavailable: ext-curl is not installed.');
        }
    }
}
