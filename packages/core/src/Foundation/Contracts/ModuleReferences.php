<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ModuleReferences
{
    public function owner(): string;

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage;
}
