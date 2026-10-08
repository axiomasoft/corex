<?php

declare(strict_types=1);

namespace CoreX\Database\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Data\SchemaSet;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SchemaSetProvider
{
    /** @return list<SchemaSet> */
    public function sets(): array;
}
