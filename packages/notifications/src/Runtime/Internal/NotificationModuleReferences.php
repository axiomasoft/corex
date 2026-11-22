<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

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
final readonly class NotificationModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-notifications';
    }

    public function moduleTables(): array
    {
        return ['ntf_notifications', 'ntf_deliveries', 'ntf_recipient_state', 'ntf_digest_runs', 'ntf_digest_items'];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'ntf_notifications' => [$tenant, ['source']],
            'ntf_deliveries' => [$tenant, ['channel', 'template_version', 'driver_version', 'provider_ticket', 'state'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(
                owner: $this->owner(), resourceKind: 'ntf_deliveries', resourceId: $id, scope: $scope, requiredAction: in_array($row['state'], ['completed', 'permanently-failed', 'exhausted', 'quarantined'], true) ? 'retain-original-receipt' : 'reconcile-original-delivery',
            )],
            'ntf_recipient_state' => [$tenant, ['kind']],
            'ntf_digest_runs' => [$tenant, ['state']],
            'ntf_digest_items' => [$tenant, ['channel']],
        ], module: $module, scope: $scope, after: $after);
    }
}
