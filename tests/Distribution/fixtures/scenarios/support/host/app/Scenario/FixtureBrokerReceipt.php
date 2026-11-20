<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Data\MessageEnvelope;

final class FixtureBrokerReceipt implements DurableMessagePublisher
{
    public function publish(MessageEnvelope $message): void
    {
        Scenario::connection('control')->table('scenario_broker_receipts')->insertOrIgnore([
            'message_id' => $message->messageId, 'digest' => $message->digest(),
        ]);
    }
}
