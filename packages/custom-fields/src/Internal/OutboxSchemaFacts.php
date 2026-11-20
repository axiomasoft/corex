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
        $event = new FieldSchemaChangeRequested($scope, $entity, $plan, $digest);
        $this->outbox->append($db, $event->envelope($messageId, $this->clock->now(), $plan, $causationId ?? $plan));
    }

    public function changed(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $base, string $target, string $mappingDigest, string $messageId, ?string $causationId = null, ?string $correlationId = null): void
    {
        $event = new FieldSchemaChanged($scope, $entity, $base, $target, $mappingDigest);
        $cause = $causationId ?? throw new InvalidArgumentException('Schema switch requires the admitted operation identity.');
        $this->outbox->append($db, $event->envelope($messageId, $this->clock->now(), $correlationId ?? $cause, $cause));
    }
}
