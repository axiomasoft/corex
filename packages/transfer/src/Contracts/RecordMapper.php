<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Transfer\Data\MappingResult;
use CoreX\Transfer\Data\MappingSnapshot;
use CoreX\Transfer\Data\SourceRow;

interface RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult;
}
