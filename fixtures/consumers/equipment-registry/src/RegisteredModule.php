<?php

declare(strict_types=1);

namespace Equipment\Registry;

use Composer\InstalledVersions;
use CoreX\Operations\Bulk\Laravel\SubmitBulkOperation;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final class RegisteredModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    public function manifest(): ModuleManifest
    {
        $manifest = Smoke::manifest();
        $operation = self::bulkOperation();
        $manifest->extenders[] = $operation;

        if (InstalledVersions::isInstalled('axiomasoft/corex-tools')) {
            $manifest->extenders[] = Api::exposeTool(
                operationId: $operation->operationId,
                operationVersion: $operation->version,
                code: 'equipment.transfer',
                toolVersion: 'v2',
                sideEffect: 'write',
                idempotency: 'domain-key',
                argumentSchema: $operation->requestSchema,
                resultSchema: $operation->responseSchema,
                parameterMap: [
                    'kind' => 'kind', 'source' => 'source', 'row_limit' => 'row_limit', 'time_limit_seconds' => 'time_limit_seconds',
                ],
            );
        }

        return $manifest;
    }

    private static function bulkOperation(): Api
    {
        $operation = Api::operation(
            id: 'equipment.bulk.transfer',
            method: 'POST',
            path: '/equipment/bulk-transfer',
            handler: SubmitBulkOperation::class,
            ability: 'equipment.bulk.transfer',
            requestSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['import', 'export']],
                    'source' => ['type' => 'string'],
                    'row_limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                    'time_limit_seconds' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                ],
                'required' => ['kind', 'source'],
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => ['operation_id' => ['type' => 'string'], 'status_url' => ['type' => 'string']],
                'required' => ['operation_id', 'status_url'],
            ],
            rules: [
                'kind' => ['required', 'string', 'in:import,export'],
                'source' => ['required', 'string'],
                'row_limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
                'time_limit_seconds' => ['sometimes', 'integer', 'min:1', 'max:30'],
            ],
            version: 'v2',
            successStatus: 202,
        )->asBulkCommand()->idempotency('required')->effect('tenant_transactional')->cost(['base' => 2]);
        $operation->bulkExecutionEnabled = true;

        return $operation;
    }

    public function tablePrefix(): string
    {
        return 'eq';
    }

    public function schemaSetProviders(): array
    {
        return [EquipmentSchema::class];
    }
}
