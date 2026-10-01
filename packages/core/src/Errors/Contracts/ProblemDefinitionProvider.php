<?php

declare(strict_types=1);

namespace CoreX\Errors\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Data\ProblemDefinition;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ProblemDefinitionProvider
{
    /** @return iterable<ProblemDefinition> */
    public function definitions(): iterable;
}
