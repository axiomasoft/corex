<?php

declare(strict_types=1);

use CoreX\Support\PackageRegistrar;
use CoreX\Support\RegistersNothingOnBoot;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as FoundationApplication;

it('records register and boots as a no-op', function (): void {
    $registrar = new class implements PackageRegistrar
    {
        use RegistersNothingOnBoot;

        public bool $registered = false;

        public function register(Application $app): void
        {
            $this->registered = true;
        }
    };

    $app = new FoundationApplication(dirname(__DIR__, 4));

    $registrar->register($app);
    $registrar->boot($app);

    expect($registrar->registered)->toBeTrue();
});
