<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Internal;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Packman\Modules\Data\Extenders\Api;

/** Canonical operation-contract/1 hash. Excludes activation, host and time. */
final class OperationContractHasher
{
    public static function hash(Api $api, string $registryFingerprint): string
    {
        $payload = [
            'schema' => 'operation-contract/1',
            'registryFingerprint' => $registryFingerprint,
            'module' => $api->declaringModule,
            'id' => $api->operationId,
            'version' => $api->version,
            'mode' => $api->operationMode,
            'method' => $api->method,
            'path' => $api->path,
            'ability' => $api->ability,
            'handler' => $api->handler,
            'scopes' => self::sorted($api->requiredScopes),
            'idempotency' => $api->idempotencyRequirement,
            'effectMode' => $api->effectMode,
            'readFields' => self::sorted($api->readFields),
            'writeFields' => self::sorted($api->writeFields),
            'pagination' => $api->pagination,
            'capabilities' => self::sorted($api->capabilities),
            'deprecation' => $api->deprecation,
            'executionSupported' => $api->executionSupported,
            'requestSchema' => JsonCanonical::encode($api->requestSchema),
            'responseSchema' => JsonCanonical::encode($api->responseSchema),
            'successStatus' => $api->successStatus,
        ];

        if ($api->cost !== null) {
            $payload['cost'] = $api->cost;
        }

        return hash('sha256', JsonCanonical::encode($payload));
    }

    /**
     * @param  list<string>|null  $values
     * @return list<string>|null
     */
    private static function sorted(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $copy = $values;
        sort($copy);

        return $copy;
    }
}
