<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\ImportExport\Support\MappingResult;
use CoreX\ImportExport\Support\MappingSnapshot;
use CoreX\ImportExport\Support\SourceRow;

interface RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult;
}
