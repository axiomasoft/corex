<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal;

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
final readonly class IntlModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-intl';
    }

    public function moduleTables(): array
    {
        return [
            'corex_rate_snapshots', 'corex_rate_batches', 'corex_rate_current', 'corex_reference_datasets',
            'corex_reference_records', 'corex_reference_aliases', 'corex_reference_current',
        ];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'corex_rate_snapshots' => [$tenant, ['source', 'publication_revision']],
            'corex_rate_batches' => [$tenant, ['generation', 'state']],
            'corex_rate_current' => [$tenant, ['source', 'generation']],
            'corex_reference_datasets' => [$tenant, ['state']],
            'corex_reference_records' => [$tenant, ['provenance', 'kind', 'namespace']],
            'corex_reference_aliases' => [$tenant, ['namespace']],
            'corex_reference_current' => [$tenant, ['generation']],
        ], module: $module, scope: $scope, after: $after);
    }
}
