<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Messaging\Contracts\BrokerDeliverySource;
use CoreX\Messaging\Data\ConsumerId;
use RuntimeException;

final class SearchScenarioBroker implements BrokerDeliverySource
{
    public function read(ConsumerId $consumer, string $restrictedRef): ?string
    {
        $row = Scenario::connection('control')->table('scenario_search_broker')->where('id', $restrictedRef)->first();

        return $row === null || $row->quarantine !== null ? null : $row->wire;
    }

    public function quarantine(ConsumerId $consumer, string $restrictedRef, string $rawDigest, string $reason): void
    {
        $database = Scenario::connection('control');
        $wire = $database->table('scenario_search_broker')->where('id', $restrictedRef)->value('wire');

        if (! is_string($wire) || ! hash_equals(hash('sha256', $wire), $rawDigest)) {
            throw new RuntimeException('The broker quarantine is outside the exact captured envelope.');
        }
        $database->table('scenario_search_broker')->where('id', $restrictedRef)->update(['quarantine' => $reason]);
    }
}
