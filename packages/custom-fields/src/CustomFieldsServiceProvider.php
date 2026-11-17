<?php

declare(strict_types=1);

namespace CoreX\CustomFields;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\CustomFields\Errors\Internal\PackageProblemDefinitions;
use CoreX\CustomFields\Internal\CustomFieldsSchemaSets;
use CoreX\CustomFields\Internal\Laravel\AttributeBindingsRegistrar;
use CoreX\CustomFields\Internal\Laravel\AttributeCompositionRegistrar;
use CoreX\CustomFields\Internal\Laravel\AttributeMessagingRegistrar;
use CoreX\Errors\Laravel\ProblemDefinitions;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CustomFieldsServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [
        AttributeMessagingRegistrar::class,
        AttributeCompositionRegistrar::class,
        AttributeBindingsRegistrar::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->app->tag([CustomFieldsSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-custom-fields.php', 'corex-custom-fields');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }
}
