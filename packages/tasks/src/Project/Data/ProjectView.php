<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ProjectView
{
    public function __construct(public string $id, public ?string $workspaceId) {}
}
