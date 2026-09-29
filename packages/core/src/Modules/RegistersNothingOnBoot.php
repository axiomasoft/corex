<?php

declare(strict_types=1);

namespace CoreX\Modules;

use Illuminate\Contracts\Foundation\Application;
use Override;

trait RegistersNothingOnBoot
{
    #[Override]
    public function boot(Application $app): void {}
}
