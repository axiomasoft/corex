<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

final class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag((new RegisteredModule)->schemaSetProviders(), SchemaSetProvider::class);
        $this->app->tag([DeclaredEquipmentFields::class], DeclaredFields::class);
        $this->app->singleton(MemoryDriver::class);
        $this->app->tag(MemoryDriver::class, [CandidateDriver::class, IndexDriver::class]);
        $this->callAfterResolving(SearchDriverRegistry::class, static function (SearchDriverRegistry $registry): void {
            $resolve = static fn (Application $app): MemoryDriver => $app->make(MemoryDriver::class);
            $registry->register(Smoke::Driver, $resolve);
            $registry->registerIndex(Smoke::Driver, $resolve);
        });
    }
}
