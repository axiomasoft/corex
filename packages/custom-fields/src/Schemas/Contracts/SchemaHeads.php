<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\CustomFields\Schemas\Data\LockedFieldMapping;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
interface SchemaHeads
{
    public function lockForWrite(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): LockedFieldMapping;

    public function effective(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): LockedFieldMapping;
}
