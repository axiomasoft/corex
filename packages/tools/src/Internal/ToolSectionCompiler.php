<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Modules\Compiler\NormalizedExtension;
use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;

/** @internal Tool declaration compilation. */
final class ToolSectionCompiler implements ManifestSectionCompiler
{
    public function mode(): string
    {
        return 'tool';
    }

    public function phase(): int
    {
        return 10;
    }

    public function compile(array $declarations, array $all): void
    {
        $operationsByModule = [];
        $toolTuples = [];
        $wireNames = [];

        foreach ($all as $api) {
            $module = $api->declaringModule ?? '';

            if ($api->mode === 'operation') {
                $operationsByModule[$module][$api->version.':'.$api->operationId] = $api;
            }
        }

        foreach ($declarations as $api) {
            $module = $api->declaringModule ?? '';

            ApiToolValidator::validate($api);

            if ($api->toolKind === 'exposed') {
                $operation = $api->operationRef === null ? null : ($operationsByModule[$module][$api->operationRef] ?? null);

                if (! $operation instanceof Api) {
                    throw new CompilationException('Unresolved operation reference for tool: '.$api->toolCode);
                }

                ApiToolValidator::validateExposedOperationReference($api, $operation, $module);
            }

            $tuple = $api->declaringModule.':'.$api->toolCode.':'.$api->toolVersion;

            if (isset($toolTuples[$tuple])) {
                throw new CompilationException('Duplicate tool tuple: '.$tuple);
            }

            $wire = ApiToolValidator::wireName($api);

            if (isset($wireNames[$wire])) {
                throw new CompilationException('Duplicate tool wire name: '.$wire);
            }

            $toolTuples[$tuple] = true;
            $wireNames[$wire] = true;
        }
    }

    public function normalize(ModuleManifest $manifest, Api $api, string $ownerVersion): array
    {
        $wire = ApiToolValidator::wireName($api);

        return [
            new NormalizedExtension(
                point: 'api.tool',
                id: $manifest->composerName.':'.$api->toolCode.':'.$api->toolVersion,
                owner: $manifest->composerName,
                ownerVersion: $ownerVersion,
                payload: ['wire' => $wire],
            ),
            new NormalizedExtension(
                point: 'api.tool',
                id: 'wire:'.$wire,
                owner: $manifest->composerName,
                ownerVersion: $ownerVersion,
                payload: ['tool' => $api->toolCode],
            ),
        ];
    }
}
