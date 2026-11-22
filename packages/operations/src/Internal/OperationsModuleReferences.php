<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use Packman\Attributes\Internal;

#[Internal]
final readonly class OperationsModuleReferences implements ModuleReferences
{
    public function __construct(private ApiSchemaConnections $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-operations';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->tenant();
        $this->current->assert(scope: $scope, connection: $tenant);
        $budget = $this->connections->budget();

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'sys_api_budget_buckets' => [$budget, ['key']],
            'sys_api_budget_reservations' => [$budget, ['recipe_hash', 'state']],
            'sys_api_idempotency' => [$tenant, ['operation_id', 'state']],
            'sys_api_bulk_operations' => [$tenant, ['operation_id', 'result_ref']],
        ], module: $module, scope: $scope, after: $after);
    }
}
