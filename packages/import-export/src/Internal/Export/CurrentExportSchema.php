<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Export;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use Packman\Attributes\Internal;
use Throwable;

#[Internal]
final readonly class CurrentExportSchema
{
    /** @param Closure(): ?ExportSchemaWitness $witness */
    public function __construct(private Closure $witness) {}

    public function assertCurrent(DataScope $scope, string $expected, JsonDocument $roster): void
    {
        try {
            $matches = ($this->witness)()?->matches($scope, $expected, $roster);
        } catch (Throwable $exception) {
            throw new ExportUnavailable('The current export schema is unavailable.', previous: $exception);
        }

        if ($matches !== true) {
            throw new ExportUnavailable('The current export schema is unavailable or has changed.');
        }
    }
}
