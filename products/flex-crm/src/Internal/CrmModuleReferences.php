<?php

declare(strict_types=1);

namespace Flex\Crm\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CrmModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'flex/crm';
    }

    public function moduleTables(): array
    {
        return [
            'crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company', 'crm_lost_reasons',
            'crm_pipelines', 'crm_stages', 'crm_leads', 'crm_deals', 'crm_deal_contacts', 'crm_activities',
            'crm_activity_targets',
        ];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert($scope, $tenant);

        return SqlReferenceInventory::scan($this->owner(), [
            'crm_sources' => [$tenant, []],
            'crm_companies' => [$tenant, []],
            'crm_contacts' => [$tenant, []],
            'crm_contact_company' => [$tenant, []],
            'crm_lost_reasons' => [$tenant, []],
            'crm_pipelines' => [$tenant, []],
            'crm_stages' => [$tenant, []],
            'crm_leads' => [$tenant, []],
            'crm_deals' => [$tenant, []],
            'crm_deal_contacts' => [$tenant, []],
            'crm_activities' => [$tenant, []],
            'crm_activity_targets' => [$tenant, []],
        ], $module, $scope, $after);
    }
}
