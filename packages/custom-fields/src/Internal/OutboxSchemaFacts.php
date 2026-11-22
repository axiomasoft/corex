<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Schemas\Contracts\SchemaFacts;
use CoreX\CustomFields\Schemas\Events\FieldSchemaChanged;
use CoreX\CustomFields\Schemas\Events\FieldSchemaChangeRequested;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class OutboxSchemaFacts implements SchemaFacts
{
    public function __construct(private Outbox $outbox, private ClockInterface $clock) {}

    public function requested(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $plan, string $digest, string $messageId, ?string $causationId = null): void
    {
        $event = new FieldSchemaChangeRequested(scope: $scope, subject: $entity, plan: $plan, digest: $digest);
        $this->outbox->append(connection: $db, message: $event->envelope(messageId: $messageId, occurredAt: $this->clock->now(), correlationId: $plan, causationId: $causationId ?? $plan));
    }

    public function changed(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $base, string $target, string $mappingDigest, string $messageId, ?string $causationId = null, ?string $correlationId = null): void
    {
        $event = new FieldSchemaChanged(scope: $scope, subject: $entity, base: $base, target: $target, mappingDigest: $mappingDigest);
        $cause = $causationId ?? throw new InvalidArgumentException('Schema switch requires the admitted operation identity.');
        $this->outbox->append(connection: $db, message: $event->envelope(messageId: $messageId, occurredAt: $this->clock->now(), correlationId: $correlationId ?? $cause, causationId: $cause));
    }
}
