<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\ImportExport\Data\MappingResult;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\SourceRow;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult;
}
