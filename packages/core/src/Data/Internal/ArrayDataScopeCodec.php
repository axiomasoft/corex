<?php

declare(strict_types=1);

namespace CoreX\Data\Internal;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Exceptions\InvalidScopePayload;
use Override;

final readonly class ArrayDataScopeCodec implements DataScopeCodec
{
    /**
     * Порядок ключей фиксирован и является частью контракта: по этому массиву
     * считаются отпечатки идемпотентности, поэтому перестановка ключей — ломающее
     * изменение, а не форматирование.
     *
     * @return array<string, scalar|null>
     */
    #[Override]
    public function encode(DataScope $scope): array
    {
        return [
            'version' => $scope->version,
            'account' => $scope->accountId,
            'workspace' => $scope->workspaceId,
            'connection' => $scope->connectionIdentity,
            'actor' => $scope->actorId,
            'restoreEpoch' => $scope->restoreEpoch,
            'boxed' => $scope->boxed,
        ];
    }

    /** @param array<string, mixed> $payload */
    #[Override]
    public function decode(array $payload): DataScope
    {
        $version = $payload['version'] ?? null;

        if (! is_int($version)) {
            throw InvalidScopePayload::missing('version');
        }

        if ($version !== DataScope::VERSION) {
            throw InvalidScopePayload::unsupportedVersion($version);
        }

        return new DataScope(
            accountId: self::requireString(payload: $payload, key: 'account'),
            workspaceId: self::optionalString(payload: $payload, key: 'workspace'),
            connectionIdentity: self::requireString(payload: $payload, key: 'connection'),
            actorId: self::requireString(payload: $payload, key: 'actor'),
            restoreEpoch: self::requireString(payload: $payload, key: 'restoreEpoch'),
            boxed: self::requireBool(payload: $payload, key: 'boxed'),
            version: $version,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function requireString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            throw InvalidScopePayload::missing($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function optionalString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload)) {
            throw InvalidScopePayload::missing($key);
        }

        $value = $payload[$key];

        if ($value !== null && ! is_string($value)) {
            throw InvalidScopePayload::missing($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function requireBool(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;

        if (! is_bool($value)) {
            throw InvalidScopePayload::missing($key);
        }

        return $value;
    }
}
