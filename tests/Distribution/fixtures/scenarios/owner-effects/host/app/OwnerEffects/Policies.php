<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use App\Scenario\Scenario;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

final class Policies implements MediaPolicy
{
    public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
    {
        $scope = Effects::scope();

        if ($context->tenant->id() !== $scope->accountId || $context->actorId !== $scope->principalId
            || $context->tenant->workspace?->id !== $scope->workspaceId || $scope->principalKind !== 'user') {
            return false;
        }
        $definition = resolve(ModuleRegistry::class)->compiled()->entities[Effects::ENTITY] ?? null;

        if ($definition?->module !== Effects::MODULE || ! is_subclass_of($definition->model, Model::class)) {
            return false;
        }
        $record = ($definition->model)::query()->find(Scenario::RECORD);

        if ($record === null || ! resolve(AccessChecker::class)->canOnRecord(auth()->user(), 'read', $record)) {
            return false;
        }

        if ($subject->file === null) {
            return $subject->attachment === null || ($subject->attachment->entityHandle === Effects::ENTITY && $subject->attachment->entityId === Scenario::RECORD);
        }

        if ($action === MediaAction::Store) {
            return DB::table('scenario_file_grants')->where(['scope' => Effects::identity($scope),
                'file' => $subject->file->kind.'/'.$subject->file->mimeType, 'ability' => $action->value])->exists();
        }

        return DB::table('scenario_file_grants')->where([
            'scope' => Effects::identity($scope), 'file' => $subject->file->id, 'ability' => $action->value,
        ])->exists();
    }
}
