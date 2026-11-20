<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SchemaOperationAdmission
{
    public function __construct(private DataScopeAuthorizer $authorizer) {}

    public function allows(DataScope $scope, string $entity, FieldUse $use): bool
    {
        return $this->authorizer->authorize($scope, new RecordRef('attributes.schema', $entity), new FieldSelection([]), $use)->allowed;
    }

    public function approves(DataScope $scope, string $digest): bool
    {
        return $this->authorizer->authorize($scope, new RecordRef('attributes.schema-approval', $digest), new FieldSelection([]), FieldUse::Write)->allowed;
    }

    public static function scopeHash(DataScope $scope): string
    {
        return hash('sha256', json_encode([DataScope::VERSION, $scope], JSON_THROW_ON_ERROR));
    }
}
