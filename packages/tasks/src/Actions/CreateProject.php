<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Enums\ProjectStatus;
use CoreX\Tasks\Enums\ProjectType;
use CoreX\Tasks\Events\ProjectCreated;
use CoreX\Tasks\Models\Project;
use Illuminate\Support\Facades\DB;

final class CreateProject
{
    public function handle(
        string $name,
        string $code,
        ProjectType $type = ProjectType::Project,
        ?string $processId = null,
        ?string $companyId = null,
    ): Project {
        return DB::transaction(function () use ($name, $code, $type, $processId, $companyId): Project {
            $project = Project::query()->create([
                'name' => $name,
                'code' => $code,
                'type' => $type,
                'status' => ProjectStatus::Active,
                'process_id' => $processId,
                'company_id' => $companyId,
            ]);

            ProjectCreated::dispatch($project);

            return $project;
        });
    }
}
