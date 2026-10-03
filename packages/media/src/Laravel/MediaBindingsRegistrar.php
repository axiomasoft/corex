<?php

declare(strict_types=1);

namespace CoreX\Media\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Contracts\RelationLookup;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\DenyMediaPolicy;
use CoreX\Media\Internal\GdThumbnailDriver;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\SqlMediaLibrary;
use CoreX\Media\Internal\SqlRelationLookup;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MediaBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        // The conversion driver tag is needed to register drivers now; this one-off read does not cache Options before boot.
        $options = MediaOptions::fromArray((array) $app->make(Repository::class)->get('corex-media', []));

        $app->singleton(MediaOptions::class, static fn (Application $app): MediaOptions => MediaOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-media', []),
        ));
        $app->singleton(MediaPolicy::class, DenyMediaPolicy::class);
        $app->scoped(MediaOperationStore::class, static fn (Application $app): MediaOperationStore => new MediaOperationStore($app->make(DatabaseManager::class)->connection(), $app->make(ClockInterface::class)));

        if (extension_loaded('gd')) {
            $app->singleton(GdThumbnailDriver::class, static fn (Application $app): GdThumbnailDriver => (new GdThumbnailDriverFactory)->make($app));
            $app->tag(GdThumbnailDriver::class, $options->conversionDriverTag);
        }

        $app->scoped(ConversionProfiles::class, static fn (Application $app): ConversionProfiles => new ConversionProfiles(self::conversionDrivers($app)));
        $app->scoped(ConversionCoordinator::class, static fn (Application $app): ConversionCoordinator => new ConversionCoordinator($app->make(DatabaseManager::class)->connection(), $app->make(TenantStorage::class), $app->make(ConversionProfiles::class), $app->make(MediaOperationStore::class), $app->make(MediaOptions::class), $app->make(ClockInterface::class)));
        $app->scoped(RelationLookup::class, static fn (Application $app): SqlRelationLookup => new SqlRelationLookup($app->make(DatabaseManager::class)->connection(), $app->make(MediaPolicy::class), $app->make(ClockInterface::class)));
        $app->scoped(MediaLibrary::class, static fn (Application $app): SqlMediaLibrary => new SqlMediaLibrary($app->make(DatabaseManager::class)->connection(), $app->make(TenantStorage::class), $app->make(MediaPolicy::class), $app->make(MediaOperationStore::class), $app->make(ConversionCoordinator::class), $app->make(MediaOptions::class), $app->make(ClockInterface::class)));
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(MediaOptions::class);
    }

    /** @return iterable<ConversionDriver> */
    private static function conversionDrivers(Application $app): iterable
    {
        /** @var iterable<ConversionDriver> $drivers */
        $drivers = $app->tagged($app->make(MediaOptions::class)->conversionDriverTag);

        return $drivers;
    }
}
