<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
trait RegistersNothingOnBoot
{
    #[Override]
    public function boot(Application $app): void {}
}
