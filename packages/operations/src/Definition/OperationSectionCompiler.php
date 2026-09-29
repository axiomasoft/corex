<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition;

use CoreX\Modules\Compiler\NormalizedExtension;
use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;

/** @internal Operation declaration compilation. */
final class OperationSectionCompiler implements ManifestSectionCompiler
{
    public function mode(): string
    {
        return 'operation';
    }

    public function phase(): int
    {
        return 0;
    }

    public function compile(array $declarations, array $all): void
    {
        $ids = [];
        $paths = [];
        $operations = [];

        foreach ($declarations as $api) {
            ApiOperationValidator::validate($api);
            $id = $api->version.':'.$api->operationId;
            $path = $api->version.':'.$api->method.':'.preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '{}', $api->path);

            if (isset($ids[$id]) || isset($paths[$path])) {
                throw new CompilationException('Duplicate API operation identifier or route: '.$id);
            }

            foreach ($operations as $other) {
                ApiOperationValidator::validateRoutePair($api, $other);
            }

            $ids[$id] = true;
            $paths[$path] = true;
            $operations[] = $api;

        }
    }

    public function normalize(ModuleManifest $manifest, Api $api, string $ownerVersion): array
    {
        return [
            new NormalizedExtension(
                point: 'api.operation',
                id: $api->version.':'.$api->operationId,
                owner: $manifest->composerName,
                ownerVersion: $ownerVersion,
                payload: [
                    'method' => $api->method,
                    'path' => preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '{}', $api->path),
                ],
            ),
        ];
    }
}
