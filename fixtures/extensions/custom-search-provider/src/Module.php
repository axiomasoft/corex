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
    public const string Name = 'thirdparty/custom-search-provider';

    public const string Driver = 'thirdparty-search';

    public const string Field = '01900000-0000-7000-8000-000000000005';

    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make(self::Name)
            ->title('Custom search provider')
            ->description('Indexes and queries equipment through the public search contract.')
            ->tablePrefix('csp')
            ->extends([
                Entity::make(BaseModel::class)->handle('csp.document')->table('csp_documents')->searchable()->customFields()->fields([new FieldDefinition('name', 'string')]),
                Entity::make(BaseModel::class)->handle('csp.note')->table('csp_notes')->fields([new FieldDefinition('name', 'string')]),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion(self::Name, '1.0.0', 'custom-search-provider');
    }

    public static function searchFixture(): array
    {
        return [
            'driver' => self::Driver, 'class' => MemoryDriver::class,
            'entity' => 'csp.document', 'record' => 'eq-1', 'title' => 'pump alpha', 'field' => self::Field,
        ];
    }

    public static function prove(): void
    {
        $report = ExtensionContract::verify(
            manifests: [self::manifest()],
            installedPackages: [self::Name => self::installedVersion()],
        );

        if (! $report->passed()) {
            throw new RuntimeException($report->summary());
        }
    }
}
