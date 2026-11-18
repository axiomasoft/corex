<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Internal;

use CoreX\Tasks\Project\Data\ProjectReference;
use CoreX\Tasks\Project\Internal\Models\Project;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
final class ProjectReferenceFactory
{
    public static function fromProject(Project $project): ProjectReference
    {
        return new ProjectReference(id: $project->id, type: ProjectReference::TYPE, display: $project->name);
    }
}
