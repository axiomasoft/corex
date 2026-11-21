<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Contracts;

use CoreX\Data\Data\FieldUsePlan;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ProjectionFilter
{
    /**
     * @param  list<string|int|bool>  $values
     * @return list<string|int|bool>
     */
    public function normalize(array $values, FieldUsePlan $grant): array;
}
