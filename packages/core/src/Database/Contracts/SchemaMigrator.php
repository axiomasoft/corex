<?php

declare(strict_types=1);

namespace CoreX\Database\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Data\SchemaSetStatus;
use CoreX\Database\Enums\SchemaScope;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface SchemaMigrator
{
    /** @return list<string> migrations run, in order */
    public function migrate(SchemaScope $scope, ?string $connection = null, bool $includeModules = false): array;

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function migrateSets(array $ids, ?string $connection = null): array;

    /** @return list<SchemaSetStatus> */
    public function status(SchemaScope $scope, ?string $connection = null): array;
}
