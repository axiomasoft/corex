<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class WorkflowModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-workflow';
    }

    public function moduleTables(): array
    {
        return ['sys_workflow_intents'];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'sys_workflow_intents' => [$tenant, ['module', 'definition_ref', 'version_ref', 'kind', 'provider_ticket', 'effect_key', 'state'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(
                owner: $this->owner(), resourceKind: 'sys_workflow_intents', resourceId: $id, scope: $scope, requiredAction: in_array($row['state'], ['confirmed', 'failed', 'quarantined'], true) ? 'retain-original-receipt' : 'reconcile-original-engine-attempt',
            )],
        ], module: $module, scope: $scope, after: $after);
    }
}
