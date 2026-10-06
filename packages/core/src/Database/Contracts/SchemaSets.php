<?php

declare(strict_types=1);

namespace CoreX\Database\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface SchemaSets
{
    /** @return list<SchemaSet> active sets, sorted by id */
    public function all(): array;

    /** @return list<SchemaSet> */
    public function forScope(SchemaScope $scope, bool $includeModules): array;

    public function get(string $id): SchemaSet;

    public function moduleSet(string $package): ?SchemaSet;
}
