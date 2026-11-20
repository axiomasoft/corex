<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Pg;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

trait FailsSchemaFacts
{
    public function requested(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $plan, string $digest, string $messageId, ?string $causationId = null): void
    {
        throw new RuntimeException('fixture-requested');
    }

    public function changed(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, string $base, string $target, string $mappingDigest, string $messageId, ?string $causationId = null, ?string $correlationId = null): void
    {
        throw new RuntimeException('fixture-changed');
    }
}
