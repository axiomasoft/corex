<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PackageRegistrar
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
