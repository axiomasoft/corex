<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemaDependencies;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SchemaReferences
{
    /** @param Closure():iterable<FieldSchemaDependencies> $owners */
    public function __construct(private Closure $owners) {}

    /** @return list<string> */
    public function get(DataScope $scope, EntityHandle $entity, string $field, string $revision): array
    {
        $refs = [];
        foreach (($this->owners)() as $owner) {
            foreach ($owner->references($scope, $entity, new FieldId($field), new SchemaRevision($revision)) as $ref) {
                if ($ref === '' || strlen($ref) > 191 || preg_match('/[\x00-\x1f\x7f]/', $ref) === 1) {
                    throw new SchemaConflict('invalid-dependency-witness');
                }
                $refs[] = $ref;
            }
        }
        sort($refs, SORT_STRING);

        return array_values(array_unique($refs));
    }
}
