<?php

declare(strict_types=1);

namespace CoreX\Recurrence;

use CoreX\Recurrence\Errors\Internal\PackageProblemDefinitions;
use Illuminate\Support\ServiceProvider;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Laravel\ProblemDefinitions;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RecurrenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

    }
}
