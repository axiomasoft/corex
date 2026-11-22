<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

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
final readonly class WakeupModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-scheduler';
    }

    public function moduleTables(): array
    {
        return ['sys_wakeup_requests'];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'sys_wakeup_requests' => [$tenant, ['kind', 'generation', 'status'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(
                owner: $this->owner(), resourceKind: 'sys_wakeup_requests', resourceId: $id, scope: $scope, requiredAction: in_array($row['status'], ['completed', 'cancelled', 'exhausted', 'quarantined'], true) ? 'retain-original-receipt' : 'drain-original-wakeup',
            )],
        ], module: $module, scope: $scope, after: $after);
    }
}
