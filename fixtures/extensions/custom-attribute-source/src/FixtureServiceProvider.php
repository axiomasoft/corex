<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldType;
use Illuminate\Support\ServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

final class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag((new RegisteredModule)->schemaSetProviders(), SchemaSetProvider::class);
        $this->app->tag([DeclaredEquipmentFields::class], DeclaredFields::class);
        $this->app->tag([InspectionCodeType::class], FieldType::class);
        $this->app->tag([InspectionCodeConverterProvider::class], FieldConverterProvider::class);
    }
}
