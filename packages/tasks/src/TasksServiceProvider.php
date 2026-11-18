<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Tasks\Errors\Internal\PackageProblemDefinitions;
use CoreX\Tasks\Laravel\TasksRegistrar;
use Illuminate\Support\ServiceProvider;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Foundation\Concerns\RegistersPackageRegistrars;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksServiceProvider extends ServiceProvider
{
    use RegistersPackageRegistrars;

    /** @var list<class-string<PackageRegistrar>> */
    protected const array REGISTRARS = [TasksRegistrar::class];

    public function register(): void
    {
        $this->app->tag((new TasksModule)->schemaSetProviders(), SchemaSetProvider::class);
        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);
        $this->registerPackageRegistrars();
    }
}
