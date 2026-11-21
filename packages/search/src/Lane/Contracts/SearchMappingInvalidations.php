<?php

declare(strict_types=1);

namespace CoreX\Search\Lane\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Messaging\Data\MessageEnvelope;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface SearchMappingInvalidations
{
    /** Record one entity rebuild request on the already verified subscriber transaction. */
    public function apply(ConnectionInterface $writer, MessageEnvelope $event, EntityHandle $entity, string $sourceRevision, string $sourceMappingDigest): void;

    /** Read the durable rebuild signal in the verified Search worker scope. */
    public function pending(DataScope $scope, EntityHandle $entity): bool;
}
