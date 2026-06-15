<?php

declare(strict_types=1);

namespace CoreX\Tasks\Events;

use CoreX\Tasks\Models\Project;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ProjectCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
    ) {}
}
