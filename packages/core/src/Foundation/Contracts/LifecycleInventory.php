<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\ModuleRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface LifecycleInventory
{
    /** @return list<DataScope> All logical scopes sharing the physical transition namespace. */
    public function scopes(DataScope $operator, ModuleRef $target): array;

    /** @return list<string> Additional registered product, external resource and configuration authorities. */
    public function owners(DataScope $scope, ModuleRef $target): array;

    /** Immutable composition, physical membership, contributions and retention witness under a durable exclusion. */
    public function revision(DataScope $operator, ModuleRef $target): string;

    /** Run a probe as the authorized inventory principal in the exact verified scope. */
    public function run(DataScope $scope, Closure $operation): mixed;
}
