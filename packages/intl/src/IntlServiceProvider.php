<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Intl\Errors\Internal\PackageProblemDefinitions;
use CoreX\Intl\Internal\IntlConfigSection;
use CoreX\Intl\Internal\IntlSchemaSets;
use CoreX\Intl\Laravel\IntlBindingsRegistrar;
use CoreX\Intl\Laravel\IntlReferenceRegistrar;
use CoreX\Intl\Money\Laravel\MoneyCast;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Compiler\FieldCastRegistry;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [IntlBindingsRegistrar::class, IntlReferenceRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->app->tag([IntlSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

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

    }

    private static function registerMoneyCast(FieldCastRegistry $casts): void
    {
        $casts->register(castClass: MoneyCast::class, type: 'money');
    }
}
