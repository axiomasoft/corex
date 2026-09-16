<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Contracts\TenantStorage;
use CoreX\Media\Commands\SweepMediaOperations;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Contracts\RelationLookup;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\DenyMediaPolicy;
use CoreX\Media\Internal\GdThumbnailDriver;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\SqlMediaLibrary;
use CoreX\Media\Internal\SqlRelationLookup;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class MediaServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-media.php', 'corex-media');
        $this->app->singleton(MediaPolicy::class, DenyMediaPolicy::class);
        $this->app->scoped(MediaOperationStore::class, static fn (Application $app): MediaOperationStore => new MediaOperationStore($app['db']->connection()));

        if (extension_loaded('gd')) {
            $this->app->singleton(GdThumbnailDriver::class, static fn (Application $app): GdThumbnailDriver => new GdThumbnailDriver(new ConversionProfile(
                key: 'thumbnail',
                version: 'gd-v1',
                maxWidth: (int) $app['config']->get('corex-media.conversion.thumbnail.max_width'),
                maxHeight: (int) $app['config']->get('corex-media.conversion.thumbnail.max_height'),
                maxPixels: (int) $app['config']->get('corex-media.conversion.thumbnail.max_pixels'),
                maxOutputBytes: (int) $app['config']->get('corex-media.conversion.thumbnail.max_output_bytes'),
            )));
            $this->app->tag(GdThumbnailDriver::class, (string) $this->app['config']->get('corex-media.conversion_driver_tag'));
        }
        $this->app->scoped(ConversionProfiles::class, static fn (Application $app): ConversionProfiles => new ConversionProfiles($app->tagged((string) $app['config']->get('corex-media.conversion_driver_tag'))));
        $this->app->scoped(ConversionCoordinator::class, static fn (Application $app): ConversionCoordinator => new ConversionCoordinator($app['db']->connection(), $app->make(TenantStorage::class), $app->make(ConversionProfiles::class), $app->make(MediaOperationStore::class)));
        $this->app->scoped(RelationLookup::class, static fn (Application $app): SqlRelationLookup => new SqlRelationLookup($app['db']->connection(), $app->make(MediaPolicy::class)));
        $this->app->scoped(MediaLibrary::class, static fn (Application $app): SqlMediaLibrary => new SqlMediaLibrary($app['db']->connection(), $app->make(TenantStorage::class), $app->make(MediaPolicy::class), $app->make(MediaOperationStore::class), $app->make(ConversionCoordinator::class)));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepMediaOperations::class]);
        }
    }
}
