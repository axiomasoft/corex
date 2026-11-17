<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Media\Internal\Commands\SweepMediaOperations;
use CoreX\Media\Internal\MediaSchemaSets;
use CoreX\Media\Internal\Support\MediaConfigSection;
use CoreX\Media\Laravel\MediaBindingsRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Vaulter\Media\VaulterMediaServiceProvider;
use Vaulter\VaulterServiceProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MediaServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [MediaBindingsRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->app->register(VaulterServiceProvider::class);
        $this->app->register(VaulterMediaServiceProvider::class);
        $this->app->tag([MediaSchemaSets::class], SchemaSetProvider::class);

        $this->app->tag([MediaConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-media.php', 'corex-media');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SweepMediaOperations::class]);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }
}
