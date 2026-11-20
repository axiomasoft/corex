<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Index\Data\IndexTarget;
use Packman\Attributes\Internal;

#[Internal]
final class SearchNamespace
{
    public static function scope(DataScope $scope): string
    {
        return hash('sha256', json_encode([$scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->restoreEpoch,
            $scope->accountId, $scope->accountIncarnation, $scope->workspaceId], JSON_THROW_ON_ERROR));
    }

    public static function generation(IndexTarget $target): string
    {
        return hash('sha256', json_encode([self::scope($target->scope), $target->entity->value, $target->backend, $target->cluster,
            $target->uid, $target->generation], JSON_THROW_ON_ERROR));
    }
}
