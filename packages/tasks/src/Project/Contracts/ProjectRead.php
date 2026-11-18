<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Contracts;

use CoreX\Tasks\Project\Data\ProjectView;
use Packman\Attributes\Api;

/** Read selected scalar fields on the caller's current tenant connection. */
#[Api(since: '1.0')]
interface ProjectRead
{
    public function find(string $id, ?string $connectionName = null): ?ProjectView;
}
