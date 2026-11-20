<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Data\Data\DataScope;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;

#[Internal]
final class WebhookDeliveryScope
{
    public static function constrain(Builder $query, DataScope $scope): Builder
    {
        foreach ([
            'version' => DataScope::VERSION,
            'deployment_id' => $scope->deploymentId,
            'account_id' => $scope->accountId,
            'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration,
            'account_incarnation' => $scope->accountIncarnation,
            'restore_epoch' => $scope->restoreEpoch,
        ] as $field => $value) {
            $query->where('scope->'.$field, $value);
        }

        return $scope->workspaceId === null
            ? $query->whereNull('scope->workspace_id')
            : $query->where('scope->workspace_id', $scope->workspaceId);
    }
}
