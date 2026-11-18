<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Contracts;

use CoreX\Tasks\Sprint\Data\SprintView;
use Packman\Attributes\Api;

/** Read selected scalar fields on the caller's current tenant connection. */
#[Api(since: '1.0')]
interface SprintRead
{
    public function find(string $id, ?string $connectionName = null): ?SprintView;
}
