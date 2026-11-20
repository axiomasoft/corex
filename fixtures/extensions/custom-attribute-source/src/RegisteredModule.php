<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final class RegisteredModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    public function manifest(): ModuleManifest
    {
        return Module::manifest();
    }

    public function tablePrefix(): string
    {
        return 'cas';
    }

    public function schemaSetProviders(): array
    {
        return [EquipmentSchema::class];
    }
}
