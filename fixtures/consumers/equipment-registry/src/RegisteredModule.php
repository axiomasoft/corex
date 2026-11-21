<?php

declare(strict_types=1);

namespace Equipment\Registry;

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
        $manifest->extenders[] = self::bulkOperation();

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
                'properties' => [
                    'input_ref' => ['type' => 'object'],
                    'extension_stamp' => ['type' => 'object'],
                    'row_limit' => ['type' => 'integer'],
                    'time_limit_seconds' => ['type' => 'integer'],
                ],
                'required' => ['input_ref', 'extension_stamp', 'row_limit', 'time_limit_seconds'],
            ],
            responseSchema: ['type' => 'object', 'properties' => ['operation_id' => ['type' => 'string']], 'required' => ['operation_id']],
            rules: ['input_ref' => ['required', 'array'], 'extension_stamp' => ['required', 'array'], 'row_limit' => ['required', 'integer'], 'time_limit_seconds' => ['required', 'integer']],
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
