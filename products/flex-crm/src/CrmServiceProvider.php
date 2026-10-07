<?php

declare(strict_types=1);

namespace Flex\Crm;

use CoreX\Database\Contracts\SchemaSetProvider;
use Flex\Crm\Internal\CrmSchemaSets;
use Flex\Crm\Internal\Value;
use Flex\Crm\Support\CrmOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([CrmSchemaSets::class], SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/flex-crm.php', 'flex-crm');

        $raw = $this->app->make(Repository::class)->get('flex-crm', []);
        $options = CrmOptions::fromArray(Value::map(is_array($raw) ? $raw : []));
        $this->app->instance(CrmOptions::class, $options);
    }

    public function boot(): void
    {
        // CrmModule declares entities; the compiled registry owns their runtime projection.
    }
}
