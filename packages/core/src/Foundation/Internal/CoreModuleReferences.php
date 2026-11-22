<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CoreModuleReferences implements ModuleReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'sys_settings' => [$tenant, ['key', 'namespace']],
            'sys_audit_log' => [$tenant, ['entity_type'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(owner: $this->owner(), resourceKind: 'sys_audit_log', resourceId: $id, scope: $scope, requiredAction: 'retain-original-receipt')],
            'sys_feature_flags' => [$tenant, ['module', 'key']],
            'sys_feature_overrides' => [$tenant, []],
            'sys_departments' => [$tenant, []],
            'sys_department_user' => [$tenant, []],
            'sys_pubsub_messages' => [$tenant, ['channel']],
            'sys_pubsub_cursors' => [$tenant, ['channel']],
            'sys_outbox_messages' => [$tenant, ['source', 'contract_version', 'status'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(
                owner: $this->owner(), resourceKind: 'sys_outbox_messages', resourceId: $id, scope: $scope, requiredAction: in_array($row['status'], ['published'], true) ? 'retain-original-receipt' : 'drain-original-message',
            )],
            'sys_inbox_messages' => [$tenant, ['source', 'consumer', 'contract_version'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(owner: $this->owner(), resourceKind: 'sys_inbox_messages', resourceId: $id, scope: $scope, requiredAction: 'retain-original-receipt')],
        ], module: $module, scope: $scope, after: $after);
    }
}
