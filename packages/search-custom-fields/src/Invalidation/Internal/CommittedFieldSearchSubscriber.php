<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Invalidation\Internal;

use CoreX\CustomFields\Schemas\Events\FieldSchemaChanged;
use CoreX\CustomFields\Values\Events\FieldValuesChanged;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Contracts\BrokerReceiver;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\BrokerReceipt;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Search\Lane\Contracts\SearchInvalidations;
use CoreX\Search\Lane\Contracts\SearchMappingInvalidations;
use CoreX\SearchCustomFields\Invalidation\Contracts\FieldSearchSubscriber;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CommittedFieldSearchSubscriber implements FieldSearchSubscriber
{
    public function __construct(private BrokerReceiver $broker, private Container $container) {}

    public function consume(string $restrictedRef): BrokerReceipt
    {
        return $this->broker->consume(new ConsumerId(self::CONSUMER, 2), $restrictedRef, $this->apply(...));
    }

    private function apply(ConnectionInterface $writer, MessageEnvelope $event): void
    {
        if ($event->source !== 'urn:corex:custom-fields' || $event->contractVersion !== 2) {
            throw InvalidMessage::identity('Search Custom Fields subscriber contract');
        }

        $subject = $event->subject;

        if ($event->name === FieldValuesChanged::NAME && $subject instanceof RecordRef) {
            $this->container->make(SearchInvalidations::class)->apply($writer, $event, new EntityHandle($subject->entity), [$subject]);

            return;
        }

        if ($event->name === FieldSchemaChanged::NAME && $subject instanceof EntityHandle) {
            $revision = $event->payload->values['target'] ?? null;
            $mapping = $event->payload->values['mapping_digest'] ?? null;

            if (! is_string($revision) || ! is_string($mapping)) {
                throw InvalidMessage::payloadShape('requires the effective schema revision and mapping digest');
            }

            $this->container->make(SearchMappingInvalidations::class)->apply($writer, $event, $subject, $revision, $mapping);

            return;
        }

        throw InvalidMessage::identity('Search Custom Fields subscriber subject or contract');
    }
}
