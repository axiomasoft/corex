<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class TenancyModuleReferences implements ModuleReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current, private TenancyOptions $options) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-tenancy';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);
        $name = $this->options->centralConnection;

        if ($name === '') {
            throw new LifecycleBlocked('Central tenancy namespace is unavailable.');
        }
        $central = $this->connections->connection($name);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'root_clusters' => [$central, ['template_version']],
            'root_templates' => [$central, []],
            'root_identities' => [$central, []],
            'root_accounts' => [$central, ['template_version']],
            'root_identity_accounts' => [$central, []],
            'root_account_products' => [$central, []],
            'root_domains' => [$central, []],
            'root_oauth_clients' => [$central, []],
            'root_idp_sessions' => [$central, []],
            'root_oauth_refresh_tokens' => [$central, []],
            'root_impersonation_grants' => [$central, []],
            'root_feature_flags' => [$central, []],
            'root_usage_snapshots' => [$central, []],
            'root_migration_batches' => [$central, []],
            'root_account_schema_state' => [$central, []],
            'root_account_events' => [$central, []],
            'root_account_exports' => [$central, ['kind']],
            'root_wakeup_hints' => [$central, []],
            'root_wakeup_reconciliation_state' => [$central, []],
            'users' => [$tenant, []],
            'wsp_workspaces' => [$tenant, []],
            'wsp_workspace_members' => [$tenant, []],
            'wsp_invitations' => [$tenant, []],
            'sessions' => [$tenant, []],
            'tnt_membership_pairs' => [$tenant, ['operation_id']],
            'tnt_membership_inbox' => [$tenant, []],
        ], module: $module, scope: $scope, after: $after);
    }
}
