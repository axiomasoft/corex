<?php

declare(strict_types=1);

namespace CoreX\Access\Internal;

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
final readonly class AccessModuleReferences implements ModuleReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-access';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'aut_roles' => [$tenant, []],
            'aut_role_matrix' => [$tenant, ['entity_key']],
            'aut_role_assignments' => [$tenant, []],
            'aut_direct_grants' => [$tenant, []],
        ], module: $module, scope: $scope, after: $after);
    }
}
