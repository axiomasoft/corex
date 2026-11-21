<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Webhooks\Contracts\WebhookDeliveryStore;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Internal\CurlWebhookTransport;
use CoreX\Webhooks\Internal\HmacWebhookSigner;
use CoreX\Webhooks\Internal\PermitPublicEgressPolicy;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookManagement;
use CoreX\Webhooks\Internal\WebhookModuleReferences;
use CoreX\Webhooks\Internal\WebhookReceiptProjector;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookRetention;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** Composition of the package; the legacy static entry point delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WebhookBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(WebhookModuleReferences::class);
        $app->tag(WebhookModuleReferences::class, ModuleReferences::class);

        // Registration below depends on enabled flags; this one-off read does not cache Options before boot.
        $options = WebhookOptionsParser::fromArray((array) $app->make(Repository::class)->get('corex-webhooks', []));

        $app->singleton(WebhookOptions::class, static fn (Application $app): WebhookOptions => WebhookOptionsParser::fromArray(
            (array) $app->make(Repository::class)->get('corex-webhooks', []),
        ));
        $app->singleton(WebhookUrlPolicy::class, static fn (Application $app): WebhookUrlPolicy => new WebhookUrlPolicy(organizationDeniedCidrs: $app->make(WebhookOptions::class)->organizationDeniedCidrs));
        $app->singletonIf(WebhookEgressPolicy::class, PermitPublicEgressPolicy::class);
        $app->singleton(WebhookRetryPolicy::class, static fn (Application $app): WebhookRetryPolicy => new WebhookRetryPolicy($app->make(WebhookOptions::class)));
        $app->singleton(WebhookTopicCatalogue::class, static fn (Application $app): WebhookTopicCatalogue => new WebhookTopicCatalogue(
            contracts: $app->make(MessageContractRegistry::class),
            topics: $app->make(WebhookOptions::class)->topics,
            projectors: self::projectors(app: $app, options: $app->make(WebhookOptions::class)),
        ));
        $app->scoped(WebhookSubscriptionStore::class, static fn (Application $app): SqlWebhookSubscriptionStore => new SqlWebhookSubscriptionStore(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            contexts: $app->make(CurrentWorkScope::class),
            catalogue: $app->make(WebhookTopicCatalogue::class),
            clock: $app->make(ClockInterface::class),
            egress: $app->make(WebhookEgressPolicy::class),
        ));
        $app->scoped(SqlWebhookDeliveryStore::class, static fn (Application $app): SqlWebhookDeliveryStore => self::deliveryStore(app: $app, options: $app->make(WebhookOptions::class)));
        $app->alias(SqlWebhookDeliveryStore::class, WebhookDeliveryStore::class);
        $app->scoped(WebhookReceiptProjector::class);
        $app->tag(WebhookReceiptProjector::class, ProviderReceiptProjector::class);
        $app->scoped(WebhookManagement::class, static fn (Application $app): WebhookManagement => new WebhookManagement(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            contexts: $app->make(CurrentWorkScope::class),
            cursors: $app->make(CursorCodec::class),
        ));
        $app->scoped(WebhookReplay::class, static fn (Application $app): WebhookReplay => new WebhookReplay(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            contexts: $app->make(CurrentWorkScope::class),
            topics: $app->make(WebhookTopicCatalogue::class),
            clock: $app->make(ClockInterface::class),
            cursors: $app->make(CursorCodec::class),
            audit: $app->make(AuditLogger::class),
            issuer: $app->make(StoredWorkIssuer::class),
            scopes: $app->make(DataScopeCodec::class),
        ));
        $app->scoped(WebhookFanout::class, static fn (Application $app): WebhookFanout => new WebhookFanout(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            inbox: $app->make(Inbox::class),
            catalogue: $app->make(WebhookTopicCatalogue::class),
            projectors: self::projectors(app: $app, options: $app->make(WebhookOptions::class)),
            clock: $app->make(ClockInterface::class),
            codec: $app->make(CloudEventCodec::class),
            scopes: $app->make(DataScopeCodec::class),
            current: $app->make(CurrentWorkScope::class),
            issuer: $app->make(StoredWorkIssuer::class),
        ));
        $app->scoped(WebhookRetention::class, static fn (Application $app): WebhookRetention => new WebhookRetention(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            contexts: $app->make(CurrentWorkScope::class),
            clock: $app->make(ClockInterface::class),
            providerLedger: static fn (): ProviderEffectLedger => Container::getInstance()->make(ProviderEffectLedger::class),
            bodyRetentionSeconds: $app->make(WebhookOptions::class)->bodyRetentionSeconds,
            historyRetentionSeconds: $app->make(WebhookOptions::class)->historyRetentionSeconds,
            replayHorizonSeconds: $app->make(WebhookOptions::class)->replayHorizonSeconds,
        ));
        $app->scoped(WebhookDeliveryWorker::class, static fn (Application $app): WebhookDeliveryWorker => self::deliveryWorker($app));

        if (! $options->deliveryEnabled) {
            return;
        }

        $app->singleton(WebhookSigner::class, static fn (Application $app): HmacWebhookSigner => self::signer($app));
        $app->singleton(WebhookHttpTransport::class, static fn (): CurlWebhookTransport => self::transport());
    }

    #[Override]
    public function boot(Application $app): void
    {
        (new WebhookHttpRegistrar)->boot($app);
    }

    private static function deliveryStore(Application $app, WebhookOptions $options): SqlWebhookDeliveryStore
    {
        return new SqlWebhookDeliveryStore(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            contexts: $app->make(CurrentWorkScope::class),
            clock: $app->make(ClockInterface::class),
            retry: $app->make(WebhookRetryPolicy::class),
            receiptRecovery: static fn (): ProviderEffectRecovery => Container::getInstance()->make(ProviderEffectRecovery::class),
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

    private static function deliveryWorker(Application $app): WebhookDeliveryWorker
    {
        self::assertDeliveryReady(app: $app, options: $app->make(WebhookOptions::class));

        return new WebhookDeliveryWorker(
            store: $app->make(SqlWebhookDeliveryStore::class),
            signer: $app->make(WebhookSigner::class),
            urlPolicy: $app->make(WebhookUrlPolicy::class),
            transport: $app->make(WebhookHttpTransport::class),
            topics: $app->make(WebhookTopicCatalogue::class),
            clock: $app->make(ClockInterface::class),
            egress: $app->make(WebhookEgressPolicy::class),
            scopeCodec: $app->make(DataScopeCodec::class),
            resolveWorker: static fn (): WebhookDeliveryWorker => Container::getInstance()->make(WebhookDeliveryWorker::class),
        );
    }

    private static function signer(Application $app): HmacWebhookSigner
    {
        if (! $app->bound(WebhookKeyRing::class)) {
            throw new RuntimeException('Webhook signing capability is unavailable: bind a host-owned WebhookKeyRing explicitly.');
        }

        return new HmacWebhookSigner($app->make(WebhookKeyRing::class));
    }

    private static function transport(): CurlWebhookTransport
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('Webhook delivery capability is unavailable: ext-curl is not installed.');
        }

        return new CurlWebhookTransport;
    }
}
