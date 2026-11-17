<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;
use Packman\Schema\Exceptions\SchemaSetException;

/** Driver projection of the actual declarations; it never changes a set definition. */
final readonly class SchemaSelectionProvider implements SchemaSetProvider, SchemaSets
{
    /** @param list<SchemaSet> $selection */
    public function __construct(private array $selection) {}

    public function sets(): array
    {
        return $this->selection;
    }

    public function all(): array
    {
        return $this->selection;
    }

    public function forScope(SchemaScope $scope, bool $includeModules): array
    {
        return array_values(array_filter($this->selection, static fn (SchemaSet $set): bool => $set->scope === $scope
            && ($includeModules || $set->lifecycle === SchemaLifecycle::Platform)));
    }

    public function get(string $id): SchemaSet
    {
        return array_find($this->selection, static fn (SchemaSet $set): bool => $set->id() === $id)
            ?? throw SchemaSetException::unknownSet($id);
    }

    public function moduleSet(string $package): ?SchemaSet
    {
        return array_find($this->selection, static fn (SchemaSet $set): bool => $set->package === $package
            && $set->lifecycle === SchemaLifecycle::Module && $set->scope === SchemaScope::Tenant);
    }
}
