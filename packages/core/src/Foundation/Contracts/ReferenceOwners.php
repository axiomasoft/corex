<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\ModuleRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ReferenceOwners
{
    /**
     * Resolve all installed owners from the trusted contribution and version stamp.
     * An incomplete or unknown historical witness must throw.
     *
     * @param  array<string,mixed>  $witness
     * @return list<ModuleRef>
     */
    public function resolve(DataScope $scope, string $producer, string $kind, array $witness): array;
}
