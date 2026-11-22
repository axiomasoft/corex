<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SchemaAccess
{
    public function __construct(private DataScopeAuthorizer $policy) {}

    public function manage(DataScope $scope, EntityHandle $entity, FieldUse $use = FieldUse::Write): void
    {
        if (! $this->policy->authorize(scope: $scope, record: new RecordRef(entity: 'fields.schema', id: $entity->value), fields: new FieldSelection([]), use: $use)->allowed) {
            throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        }
    }
}
