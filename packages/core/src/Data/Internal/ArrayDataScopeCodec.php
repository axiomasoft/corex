<?php

declare(strict_types=1);

namespace CoreX\Data\Internal;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Exceptions\InvalidScopePayload;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ArrayDataScopeCodec implements DataScopeCodec
{
    public function encode(DataScope $scope): array
    {
        return [
            'version' => DataScope::VERSION,
            'deployment_id' => $scope->deploymentId,
            'account_id' => $scope->accountId,
            'workspace_id' => $scope->workspaceId,
            'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration,
            'account_incarnation' => $scope->accountIncarnation,
            'restore_epoch' => $scope->restoreEpoch,
            'principal_kind' => $scope->principalKind,
            'principal_id' => $scope->principalId,
        ];
    }

    public function decode(array $payload): DataScope
    {
        $version = $payload['version'] ?? null;

        if (! is_int($version)) {
            throw InvalidScopePayload::missing('version');
        }

        if ($version !== DataScope::VERSION) {
            throw InvalidScopePayload::unsupportedVersion($version);
        }
        foreach (['deployment_id', 'account_id', 'binding_id', 'account_incarnation', 'restore_epoch', 'principal_kind', 'principal_id'] as $key) {
            if (! is_string($payload[$key] ?? null)) {
                throw InvalidScopePayload::missing($key);
            }
        }

        if (! array_key_exists('workspace_id', $payload) || ($payload['workspace_id'] !== null && ! is_string($payload['workspace_id']))) {
            throw InvalidScopePayload::missing('workspace_id');
        }

        if (! is_int($payload['binding_generation'] ?? null) || count($payload) !== 10) {
            throw InvalidScopePayload::missing('scope');
        }

        try {
            return new DataScope(deploymentId: $payload['deployment_id'], accountId: $payload['account_id'], workspaceId: $payload['workspace_id'], bindingId: $payload['binding_id'],
                bindingGeneration: $payload['binding_generation'], accountIncarnation: $payload['account_incarnation'], restoreEpoch: $payload['restore_epoch'], principalKind: $payload['principal_kind'], principalId: $payload['principal_id']);
        } catch (InvalidArgumentException) {
            throw InvalidScopePayload::missing('scope');
        }
    }
}
