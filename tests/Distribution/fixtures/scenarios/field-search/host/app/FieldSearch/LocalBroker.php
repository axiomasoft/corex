<?php

declare(strict_types=1);

namespace App\FieldSearch;

use App\Scenario\Scenario;
use CoreX\Messaging\Contracts\BrokerDeliverySource;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use RuntimeException;

final readonly class LocalBroker implements BrokerDeliverySource, DurableMessagePublisher
{
    public function __construct(private CloudEventCodec $codec) {}

    public function publish(MessageEnvelope $message): void
    {
        $database = Scenario::connection('control');
        $wire = $this->codec->encode($message);
        $digest = hash('sha256', $wire);
        $broker = $database->table('scenario_field_search_broker');
        $broker->insertOrIgnore(['id' => $message->messageId, 'wire' => $wire, 'digest' => $digest]);

        if ($broker->where('id', $message->messageId)->value('digest') !== $digest) {
            throw new RuntimeException('Broker replay changed the bytes of a known message.');
        }
    }

    public function read(ConsumerId $consumer, string $restrictedRef): ?string
    {
        $row = Scenario::connection('control')->table('scenario_field_search_broker')->where('id', $restrictedRef)->first();

        return $row === null || $row->quarantine !== null ? null : $row->wire;
    }

    public function quarantine(ConsumerId $consumer, string $restrictedRef, string $rawDigest, string $reason): void
    {
        $updated = Scenario::connection('control')->table('scenario_field_search_broker')->where(['id' => $restrictedRef, 'digest' => $rawDigest])
            ->update(['quarantine' => $reason]);

        if ($updated !== 1) {
            throw new RuntimeException('The relay did not retain the exact restricted delivery.');
        }
    }
}
