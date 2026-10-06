<?php

declare(strict_types=1);

namespace CoreX\Database\Data;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class SchemaSetStatus
{
    /**
     * @param  list<string>  $applied
     * @param  list<string>  $unknown
     * @param  list<string>  $pending
     */
    public function __construct(public SchemaSet $set, public array $applied, public array $pending, public array $unknown = []) {}
}
