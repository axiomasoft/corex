<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Data\Data\DataScope;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;

#[Internal]
final class WebhookDeliveryScope
{
    /** @return array{account_id: string, account_incarnation: string, workspace_scope: string} */
    public static function logical(DataScope $scope): array
    {
        return [
            'account_id' => $scope->accountId,
            'account_incarnation' => $scope->accountIncarnation,
            'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId,
        ];
    }

    /** @return array{deployment_id: string, binding_id: string, binding_generation: int, restore_epoch: string} */
    public static function fence(DataScope $scope): array
    {
        return [
            'deployment_id' => $scope->deploymentId,
            'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration,
            'restore_epoch' => $scope->restoreEpoch,
        ];
    }

    public static function constrain(Builder $query, DataScope $scope): Builder
    {
        return $query->where(self::logical($scope))->where(self::fence($scope));
    }
}
