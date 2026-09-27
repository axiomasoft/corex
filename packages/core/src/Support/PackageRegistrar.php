<?php

declare(strict_types=1);

namespace CoreX\Support;

use Illuminate\Contracts\Foundation\Application;

interface PackageRegistrar
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
