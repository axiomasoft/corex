<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Data\Data\DataScope;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use Packman\Attributes\Internal;

#[Internal]
final readonly class NotificationScope
{
    public function __construct(private DataScopeFactory $factory) {}

    public function of(NotificationContext $context): DataScope
    {
        $scope = $this->factory->forCurrentPrincipal($context->tenant);

        if ($scope->principalKind !== $context->actorKind || $scope->principalId !== $context->actorId) {
            throw new NotificationAccessDenied('Notification context does not match the active principal.');
        }

        return $scope;
    }

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
}
