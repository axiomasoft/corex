<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class TransferModuleReferences implements ModuleReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-import-export';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert($scope, $tenant);

        return SqlReferenceInventory::scan($this->owner(), [
            'corex_transfer_jobs' => [$tenant, ['source_hash', 'mapping_hash', 'state']],
            'corex_transfer_rows' => [$tenant, ['job_id']],
            'corex_transfer_refs' => [$tenant, ['entity', 'job_id', 'state']],
            'corex_transfer_outbox' => [$tenant, ['job_id', 'state']],
            'corex_export_manifests' => [$tenant, ['job_id', 'state']],
            'corex_export_parts' => [$tenant, ['state']],
            'corex_transfer_steps' => [$tenant, ['job_id']],
        ], $module, $scope, $after);
    }
}
