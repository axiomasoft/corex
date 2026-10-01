<?php

declare(strict_types=1);

namespace Flex\Crm;

use Flex\Crm\Support\CrmOptions;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flex-crm.php', 'flex-crm');

        $raw = $this->app['config']->get('flex-crm', []);
        $options = CrmOptions::fromArray(is_array($raw) ? $raw : []);
        $this->app->instance(CrmOptions::class, $options);
    }

    public function boot(): void
    {
        // CrmModule declares entities; the compiled registry owns their runtime projection.
    }
}
