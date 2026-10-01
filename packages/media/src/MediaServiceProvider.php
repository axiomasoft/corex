<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Contracts\RelationLookup;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Internal\Commands\SweepMediaOperations;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\DenyMediaPolicy;
use CoreX\Media\Internal\GdThumbnailDriver;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\SqlMediaLibrary;
use CoreX\Media\Internal\SqlRelationLookup;
use CoreX\Media\Internal\Support\MediaConfigSection;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MediaServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([MediaConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-media.php', 'corex-media');

        $raw = $this->app['config']->get('corex-media', []);
        $options = MediaOptions::fromArray(is_array($raw) ? $raw : []);
        $this->app->instance(MediaOptions::class, $options);

        $this->app->singleton(MediaPolicy::class, DenyMediaPolicy::class);
        $this->app->scoped(MediaOperationStore::class, static fn (Application $app): MediaOperationStore => new MediaOperationStore($app['db']->connection()));

        if (extension_loaded('gd')) {
            $this->app->singleton(GdThumbnailDriver::class, static function (Application $app) use ($options): GdThumbnailDriver {
                $thumbnail = is_array($options->conversion['thumbnail'] ?? null) ? $options->conversion['thumbnail'] : [];

                return new GdThumbnailDriver(new ConversionProfile(
                    key: 'thumbnail',
                    version: 'gd-v1',
                    maxWidth: (int) ($thumbnail['max_width'] ?? 4096),
                    maxHeight: (int) ($thumbnail['max_height'] ?? 4096),
                    maxPixels: (int) ($thumbnail['max_pixels'] ?? 20_000_000),
                    maxOutputBytes: (int) ($thumbnail['max_output_bytes'] ?? 10 * 1024 * 1024),
                ));
            });
            $this->app->tag(GdThumbnailDriver::class, $options->conversionDriverTag);
        }
        $this->app->scoped(ConversionProfiles::class, static fn (Application $app): ConversionProfiles => new ConversionProfiles($app->tagged($app->make(MediaOptions::class)->conversionDriverTag)));
        $this->app->scoped(ConversionCoordinator::class, static fn (Application $app): ConversionCoordinator => new ConversionCoordinator($app['db']->connection(), $app->make(TenantStorage::class), $app->make(ConversionProfiles::class), $app->make(MediaOperationStore::class), $app->make(MediaOptions::class)));
        $this->app->scoped(RelationLookup::class, static fn (Application $app): SqlRelationLookup => new SqlRelationLookup($app['db']->connection(), $app->make(MediaPolicy::class)));
        $this->app->scoped(MediaLibrary::class, static fn (Application $app): SqlMediaLibrary => new SqlMediaLibrary($app['db']->connection(), $app->make(TenantStorage::class), $app->make(MediaPolicy::class), $app->make(MediaOperationStore::class), $app->make(ConversionCoordinator::class), $app->make(MediaOptions::class)));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepMediaOperations::class]);
        }
    }
}
