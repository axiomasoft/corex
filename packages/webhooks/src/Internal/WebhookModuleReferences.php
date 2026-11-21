<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

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
final readonly class WebhookModuleReferences implements ModuleReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-webhooks';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert($scope, $tenant);

        return SqlReferenceInventory::scan($this->owner(), [
            'sys_api_webhook_subscriptions' => [$tenant, ['topic', 'topic_version']],
            'sys_api_webhook_deliveries' => [$tenant, ['topic', 'topic_version', 'provider_ticket', 'effect_key', 'status'], fn (array $row, string $id): LifecycleBlocker => new LifecycleBlocker(
                $this->owner(), 'sys_api_webhook_deliveries', $id, $scope, in_array($row['status'], ['succeeded', 'dead', 'quarantined'], true) ? 'retain-original-receipt' : 'reconcile-original-webhook',
            )],
            'sys_api_webhook_attempts' => [$tenant, []],
        ], $module, $scope, $after);
    }
}
