<?php

declare(strict_types=1);

namespace App\Lifecycle;

use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Data\Lifecycle\ModuleDependency;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final readonly class HostModule implements ModuleDiscovery
{
    public const string PACKAGE = 'laravel/laravel';

    public function __construct(private ModuleDiscovery $installed) {}

    public function discover(): array
    {
        return [...$this->installed->discover(), ModuleManifest::make(self::PACKAGE)->title('Installed inspection host')
            ->tablePrefix('sih')->requires([ModuleDependency::module(Inventory::MODULE)])];
    }
}
