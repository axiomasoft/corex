<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
interface SchemaFacts
{
    public function requested(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $plan, string $digest, string $messageId, ?string $causationId = null): void;

    public function changed(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $base, string $target, string $mappingDigest, string $messageId, ?string $causationId = null, ?string $correlationId = null): void;
}
