<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use Illuminate\Support\ServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

final class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag((new RegisteredModule)->schemaSetProviders(), SchemaSetProvider::class);
        $this->app->tag([DeclaredEquipmentFields::class], DeclaredFields::class);
    }
}
