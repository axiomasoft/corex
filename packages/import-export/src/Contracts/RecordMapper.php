<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\ImportExport\Data\MappingResult;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\SourceRow;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult;
}
