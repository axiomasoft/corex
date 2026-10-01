<?php

declare(strict_types=1);

namespace ThirdParty\Search;

use CoreX\Data\Data\DataScope;
use CoreX\Database\Laravel\BaseModel;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\FieldDefinition;
use CoreX\Modules\Data\InstalledPackageVersion;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Testing\ExtensionContract;
use CoreX\Search\Contracts\IndexDriver;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\SearchQuery;
use RuntimeException;

final class Module
{
    private static ?SearchProvider $bound = null;

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

    public static function driver(): MemoryDriver
    {
        return new MemoryDriver;
    }

    public static function bind(SearchProvider $provider): void
    {
        self::$bound = $provider;
    }

    public static function prove(): void
    {
        $provider = self::$bound;

        if (! $provider instanceof IndexDriver) {
            throw new RuntimeException('Search manager did not return the fixture index driver.');
        }

        $scope = new DataScope('account-1', null, 'sqlite-memory', 'actor-1', hash('sha256', 'search-epoch'));
        $submission = $provider->submit(new IndexBatch('generation-1', [
            ['id' => 'eq-1', 'entity' => 'equipment', 'text' => 'pump alpha'],
        ]));
        $status = $provider->status($submission);

        if ($status->status !== 'ready') {
            throw new RuntimeException('Index submission was not ready.');
        }

        $page = $provider->query($scope, new SearchQuery('equipment', hash('sha256', 'schema'), 'pump'));

        if (count($page->candidates) !== 1 || $page->candidates[0]->record->id !== 'eq-1') {
            throw new RuntimeException('Indexed equipment was not returned by the public search provider.');
        }

        $report = ExtensionContract::verify(
            manifests: [self::manifest()],
            installedPackages: ['thirdparty/custom-search-provider' => self::installedVersion()],
        );

        if (! $report->passed()) {
            throw new RuntimeException($report->summary());
        }
    }
}
