<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ScopeAuthority
{
    public function resolve(AccountRef $account): PhysicalBinding;
}
