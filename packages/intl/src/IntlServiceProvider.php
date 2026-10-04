<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Intl\Errors\Internal\PackageProblemDefinitions;
use CoreX\Intl\Internal\IntlConfigSection;
use CoreX\Intl\Laravel\IntlBindingsRegistrar;
use CoreX\Intl\Laravel\IntlReferenceRegistrar;
use CoreX\Intl\Money\Laravel\MoneyCast;
use CoreX\Modules\Contracts\FieldCastRegistry;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [IntlBindingsRegistrar::class, IntlReferenceRegistrar::class];

    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        $this->app->tag([IntlConfigSection::class], ConfigReportSection::class);

        $this->callAfterResolving(FieldCastRegistry::class, self::registerMoneyCast(...));

        $this->mergeConfigFrom(__DIR__.'/../config/corex-intl.php', 'corex-intl');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }

        $this->publishes([
            __DIR__.'/../config/corex-intl.php' => config_path('corex-intl.php'),
        ], 'corex-intl-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    private static function registerMoneyCast(FieldCastRegistry $casts): void
    {
        $casts->register(MoneyCast::class, 'money');
    }
}
