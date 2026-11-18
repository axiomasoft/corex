<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Internal;

use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Compiler\ManifestSectionCompiler;
use Packman\Modules\Data\Compiler\NormalizedExtension;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Exceptions\Compiler\CompilationException;

/** @internal Operation declaration compilation. */
#[Internal]
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
                ApiOperationValidator::validateRoutePair(first: $api, second: $other);
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
