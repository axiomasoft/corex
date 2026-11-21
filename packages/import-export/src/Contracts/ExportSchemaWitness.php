<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ExportSchemaWitness
{
    /** Verify the expected schema against the current owner projection for this scope and roster. */
    public function matches(DataScope $scope, string $expectedHash, JsonDocument $roster): bool;
}
