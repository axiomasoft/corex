<?php

declare(strict_types=1);

namespace CoreX\Messaging\Laravel;

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\BrokerReceiver;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Contracts\PubSub;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Contracts\WorkerRuntime;
use CoreX\Messaging\Enums\PubSubDriver;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\DatabasePollPubSub;
use CoreX\Messaging\Internal\DefaultWorkerRuntime;
use CoreX\Messaging\Internal\DurableBrokerReceiver;
use CoreX\Messaging\Internal\JsonCloudEventCodec;
use CoreX\Messaging\Internal\NullPubSub;
use CoreX\Messaging\Internal\NullTraceContextBridge;
use CoreX\Messaging\Internal\RedisPubSub;
use CoreX\Messaging\Internal\SqlDeliveryHealth;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Messaging\Internal\SqlOutbox;
use CoreX\Messaging\Internal\SqlOutboxRelay;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\ApplicationLocator;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MessagingRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(
            MessageContractRegistry::class,
            static fn (): ArrayMessageContractRegistry => new ArrayMessageContractRegistry(contracts: []),
        );
        $app->scoped(BrokerReceiver::class, static fn (): DurableBrokerReceiver => new DurableBrokerReceiver(ApplicationLocator::current(...)));
        $app->scoped(CloudEventCodec::class, static fn (Application $app): JsonCloudEventCodec => new JsonCloudEventCodec(
            scopes: $app->make(DataScopeCodec::class),
            contracts: $app->make(MessageContractRegistry::class),
        ));
        $app->instance(TraceContextBridge::class, new NullTraceContextBridge);
        $app->scoped(
            Outbox::class,
            static fn (Application $app): SqlOutbox => new SqlOutbox(
                scopes: $app->make(DataScopeCodec::class),
                current: $app->make(CurrentWorkScope::class),
                issuer: $app->make(StoredWorkIssuer::class),
                contracts: $app->make(MessageContractRegistry::class),
                clock: $app->make(ClockInterface::class),
                traces: $app->make(TraceContextBridge::class),
                table: 'sys_outbox_messages',
            ),
        );
        $app->scoped(
            OutboxRelay::class,
            static fn (Application $app): SqlOutboxRelay => new SqlOutboxRelay(
                current: $app->make(CurrentWorkScope::class),
                scopes: $app->make(DataScopeCodec::class),
                connection: $app->make(Connection::class),
                clock: $app->make(ClockInterface::class),
                leaseSeconds: $app->make(CoreOptions::class)->messagingLeaseSeconds,
                maxAttempts: $app->make(CoreOptions::class)->messagingMaxAttempts,
                backoffCapSeconds: $app->make(CoreOptions::class)->messagingBackoffCapSeconds,
            ),
        );
        $app->scoped(
            Inbox::class,
            static fn (Application $app): SqlInbox => new SqlInbox(
                current: $app->make(CurrentWorkScope::class),
                contracts: $app->make(MessageContractRegistry::class),
                clock: $app->make(ClockInterface::class),
                table: 'sys_inbox_messages',
            ),
        );
        $app->scoped(
            SqlDeliveryHealth::class,
            static fn (Application $app): SqlDeliveryHealth => new SqlDeliveryHealth(
                connection: $app->make(ConnectionInterface::class),
                clock: $app->make(ClockInterface::class),
                table: 'sys_outbox_messages',
            ),
        );
        $app->singleton(PubSub::class, static fn (Application $app): PubSub => $app->make(match ($app->make(CoreOptions::class)->pubsubDriver) {
            PubSubDriver::Redis => RedisPubSub::class,
            PubSubDriver::Database => DatabasePollPubSub::class,
            PubSubDriver::Null => NullPubSub::class,
        }));
        $app->singleton(WorkerRuntime::class, static fn (): DefaultWorkerRuntime => new DefaultWorkerRuntime(ApplicationLocator::current(...)));
    }

    #[Override]
    public function boot(Application $app): void {}
}
