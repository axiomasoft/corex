<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Packman\Modules\Data\Extenders\Api;

/** Deterministic contract hash for one compiled tool declaration. */
final class ToolContractHasher
{
    public static function hash(Api $api): string
    {
        $payload = [
            'module' => $api->declaringModule,
            'code' => $api->toolCode,
            'toolVersion' => $api->toolVersion,
            'title' => $api->title,
            'description' => $api->description,
            'ability' => $api->ability,
            'sideEffect' => $api->sideEffect,
            'idempotency' => $api->idempotency,
            'handler' => $api->handler,
            'inputSchema' => JsonCanonical::encode($api->requestSchema),
            'outputSchema' => JsonCanonical::encode($api->responseSchema),
            'operationRef' => $api->operationRef,
            'parameterMap' => $api->parameterMap,
            'availability' => JsonCanonical::encode($api->availability),
            'toolKind' => $api->toolKind,
        ];

        return hash('sha256', JsonCanonical::encode($payload));
    }
}
