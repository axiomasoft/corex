<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Transfer\Data\MappingResult;
use CoreX\Transfer\Data\MappingSnapshot;
use CoreX\Transfer\Data\SourceRow;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult;
}
