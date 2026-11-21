<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class BulkReferences
{
    /** @return list<ModuleRef> */
    public static function targets(BulkOperationDefinition $definition): array
    {
        return self::stampTargets(JsonDocuments::map($definition->extensionStamp));
    }

    /** @param array<array-key, mixed> $stamp
     * @return list<ModuleRef>
     */
    public static function stampTargets(array $stamp): array
    {
        $owners = $stamp['owners'] ?? [];

        if (! is_array($owners) || ! array_is_list($owners) || count($owners) > 32) {
            throw new InvalidArgumentException('Invalid bulk owner provenance.');
        }

        $targets = [];
        foreach ($owners as $owner) {
            if (! is_string($owner)) {
                throw new InvalidArgumentException('Invalid bulk owner provenance.');
            }
            $targets[$owner] = new ModuleRef($owner);
        }

        return array_values($targets);
    }
}
