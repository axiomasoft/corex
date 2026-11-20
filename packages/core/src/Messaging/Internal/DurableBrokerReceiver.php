<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Closure;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\BrokerDeliverySource;
use CoreX\Messaging\Contracts\BrokerReceiver;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Enums\BrokerReceipt;
use CoreX\Messaging\Exceptions\InboxRefused;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DurableBrokerReceiver implements BrokerReceiver
{
    /** @param Closure():Container $application */
    public function __construct(private Closure $application) {}

    public function consume(ConsumerId $consumer, string $restrictedRef, Closure $apply): BrokerReceipt
    {
        $host = ($this->application)();
        $source = $host->make(BrokerDeliverySource::class);
        $wire = $source->read($consumer, $restrictedRef);

        if ($wire === null) {
            return BrokerReceipt::Quarantined;
        }

        try {
            [$message,$schema] = JsonCloudEventCodec::parse($wire, $host->make(DataScopeCodec::class));
            $result = $host->make(StoredWorkRunner::class)->runStored($message->scope, $message->workProof(), function () use ($message, $schema, $consumer, $apply) {
                $app = ($this->application)();

                try {
                    JsonCloudEventCodec::validate($message, $schema, $app->make(MessageContractRegistry::class));
                } catch (InvalidMessage|UnknownMessageContract $invalid) {
                    return $invalid;
                }
                $connection = $app->make(DatabaseManager::class)->connection();

                if ($connection->transactionLevel() !== 0) {
                    throw new LogicException('Broker delivery requires its own local commit before ACK.');
                }

                try {
                    return $connection->transaction(function () use ($app, $connection, $consumer, $message, $apply): BrokerReceipt {
                        if (! $app->make(Inbox::class)->claim($connection, $consumer, $message)) {
                            return BrokerReceipt::Duplicate;
                        }
                        $apply($connection, $message);

                        return BrokerReceipt::Applied;
                    });
                } catch (InboxRefused $refusal) {
                    return $refusal;
                }
            });

            if ($result instanceof InvalidMessage || $result instanceof UnknownMessageContract || $result instanceof InboxRefused) {
                throw $result;
            }

            return $result;
        } catch (InvalidMessage|UnknownMessageContract|ScopeContextMismatch|InboxRefused $failure) {
            $source->quarantine($consumer, $restrictedRef, hash('sha256', $wire), get_class($failure));

            return BrokerReceipt::Quarantined;
        }
    }
}
