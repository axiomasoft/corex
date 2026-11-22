<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

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
final readonly class FieldModuleReferences implements ModuleReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current, private DirectDdlRoute $route) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-custom-fields';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'sys_field_schema_heads' => [$tenant, ['entity']],
            'sys_attribute_documents' => [$tenant, ['entity']],
            'sys_attribute_write_tokens' => [$tenant, ['entity']],
            'sys_attribute_schema_operations' => [$tenant, ['entity', 'generation', 'state', 'plan', 'ddl_receipt', 'ddl_intent', ...array_keys(SchemaRows::fence($scope))], function (array $row, string $id) use ($scope, $tenant): LifecycleBlocker {
                $spec = SchemaRows::decode($row['plan'])['ddl'] ?? null;

                if ($spec !== null && ($row['ddl_receipt'] !== null || $row['ddl_intent'] !== null)) {
                    $spec = SchemaValue::map($spec);
                    foreach (SchemaRows::fence($scope) as $field => $expected) {
                        if ((is_int($expected) ? SchemaValue::integer($row[$field]) : SchemaValue::text($row[$field])) !== $expected) {
                            return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'field-index-plan', resourceId: $id, scope: $scope, requiredAction: 'reconcile-original-physical-owner');
                        }
                    }

                    if ($this->route->namespace(db: $tenant, scope: $scope) !== $spec['namespace']) {
                        return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'field-index-plan', resourceId: $id, scope: $scope, requiredAction: 'restore-index-namespace');
                    }
                    IndexCatalogue::verifyParent(db: $tenant, spec: $spec);
                    $index = IndexCatalogue::inspect(db: $tenant, spec: $spec);

                    if ($index !== null) {
                        $oid = SchemaValue::text($index->oid);
                        $receipt = $row['ddl_receipt'] === null ? null : SchemaRows::decode($row['ddl_receipt']);
                        $valid = $index->indisvalid === true && $index->indisready === true && $index->indislive === true;
                        $action = $receipt !== null && ($receipt['oid'] ?? null) !== $oid
                            ? 'reconcile-replaced-index' : ($valid ? 'retire-owned-index' : 'retire-invalid-index');

                        return new LifecycleBlocker(owner: $this->owner(), resourceKind: $valid ? 'field-index' : 'invalid-cic', resourceId: $oid, scope: $scope, requiredAction: $action);
                    }
                }

                return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'sys_attribute_schema_operations', resourceId: $id, scope: $scope,
                    requiredAction: in_array($row['state'], ['completed', 'cancelled'], true) ? 'remove-or-rebind-reference' : 'drain-schema-operation');
            }],
            'sys_attribute_definitions' => [$tenant, ['entity', 'owner', 'provenance', 'field_id']],
            'sys_attribute_key_reservations' => [$tenant, ['entity', 'operation_id', 'field_id']],
            'sys_attribute_backfills' => [$tenant, ['operation_id', 'kind']],
        ], module: $module, scope: $scope, after: $after);
    }
}
