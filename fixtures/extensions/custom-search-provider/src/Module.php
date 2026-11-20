<?php

declare(strict_types=1);

namespace ThirdParty\Search;

use CoreX\Database\Laravel\BaseModel;
use Packman\Modules\Data\Compiler\InstalledPackageVersion;
use Packman\Modules\Data\Entities\Entity;
use Packman\Modules\Data\Entities\FieldDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Testing\ExtensionContract;
use RuntimeException;

final class Module
{
    public const string Driver = 'thirdparty-search';

    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make('thirdparty/custom-search-provider')
            ->title('Custom search provider')
            ->description('Indexes and queries equipment through the public search contract.')
            ->tablePrefix('csp')
            ->extends([
                Entity::make(BaseModel::class)->handle('csp.document')->table('csp_documents')->searchable()->fields([new FieldDefinition('name', 'string')]),
                Entity::make(BaseModel::class)->handle('csp.note')->table('csp_notes')->fields([new FieldDefinition('name', 'string')]),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion('thirdparty/custom-search-provider', '1.0.0', 'custom-search-provider');
    }

    public static function searchFixture(): array
    {
        return [
            'driver' => self::Driver, 'class' => MemoryDriver::class,
            'entity' => 'csp.document', 'record' => 'eq-1', 'title' => 'pump alpha',
        ];
    }

    public static function prove(): void
    {
        $report = ExtensionContract::verify(
            manifests: [self::manifest()],
            installedPackages: ['thirdparty/custom-search-provider' => self::installedVersion()],
        );

        if (! $report->passed()) {
            throw new RuntimeException($report->summary());
        }
    }
}
