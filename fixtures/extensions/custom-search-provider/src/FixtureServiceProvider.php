<?php

declare(strict_types=1);

namespace ThirdParty\Search;

use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MemoryDriver::class);
        $this->app->tag(MemoryDriver::class, [CandidateDriver::class, IndexDriver::class]);
        $this->callAfterResolving(SearchDriverRegistry::class, static function (SearchDriverRegistry $registry): void {
            $resolve = static fn (Application $app): MemoryDriver => $app->make(MemoryDriver::class);
            $registry->register(Module::Driver, $resolve);
            $registry->registerIndex(Module::Driver, $resolve);
        });
    }
}
