<?php

declare(strict_types=1);

namespace CoreX\Recurrence;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Recurrence\Errors\Internal\PackageProblemDefinitions;
use Illuminate\Support\ServiceProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RecurrenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

    }
}
