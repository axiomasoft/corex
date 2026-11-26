<?php

declare(strict_types=1);

namespace Flex\Crm;

use CoreX\Foundation\Contracts\ModuleReferences;
use Flex\Crm\Internal\CrmModuleReferences;
use Flex\Crm\Internal\Value;
use Flex\Crm\Support\CrmOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CrmModuleReferences::class);
        $this->app->tag(CrmModuleReferences::class, ModuleReferences::class);

        $this->app->tag((new CrmModule)->schemaSetProviders(), SchemaSetProvider::class);

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
