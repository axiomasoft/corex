<?php

declare(strict_types=1);

namespace CoreX\Media\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Media\Attachments\Contracts\MediaAttachments;
use CoreX\Media\Attachments\Laravel\SqlMediaAttachments;
use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Conversions\Contracts\ConversionOperations;
use CoreX\Media\Conversions\Laravel\ConversionCoordinator;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Files\Laravel\SqlMediaLibrary;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\DenyMediaPolicy;
use CoreX\Media\Internal\GdThumbnailDriver;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaModuleReferences;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Support\MediaOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;
use Vaulter\Database\VaulterDatabase;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MediaBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(MediaModuleReferences::class);
        $app->tag(MediaModuleReferences::class, ModuleReferences::class);

        // The conversion driver tag is needed to register drivers now; this one-off read does not cache Options before boot.
        $options = MediaOptions::fromArray((array) $app->make(Repository::class)->get('corex-media', []));

        $app->singleton(MediaOptions::class, static fn (Application $app): MediaOptions => MediaOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-media', []),
        ));
        $app->singleton(MediaPolicy::class, DenyMediaPolicy::class);
        $app->scoped(MediaBlobs::class, static fn (Application $app): MediaBlobs => new MediaBlobs(database: $app->make(VaulterDatabase::class), connections: $app->make(DatabaseManager::class), store: $app->make(BlobStore::class), pins: $app->make(BlobPins::class), tenants: $app->make(TenantContextResolver::class), scopes: $app->make(DataScopeFactory::class)));
        $app->scoped(MediaOperationStore::class, static fn (Application $app): MediaOperationStore => new MediaOperationStore(connection: $app->make(DatabaseManager::class)->connection(), clock: $app->make(ClockInterface::class), blobs: $app->make(MediaBlobs::class),
            contexts: $app->make(TenantContextResolver::class), scopes: $app->make(DataScopeFactory::class),
            current: $app->make(CurrentWorkScope::class), codec: $app->make(DataScopeCodec::class),
            issuer: $app->make(StoredWorkIssuer::class), admission: $app->make(ReferenceAdmission::class), runner: $app->make(StoredWorkRunner::class),
        ));

        if (extension_loaded('gd')) {
            $app->singleton(GdThumbnailDriver::class, static fn (Application $app): ConversionDriver => (new GdThumbnailDriverFactory)->make($app));
            $app->tag(GdThumbnailDriver::class, $options->conversionDriverTag);
        }

        $app->scoped(ConversionProfiles::class, static fn (Application $app): ConversionProfiles => new ConversionProfiles(self::conversionDrivers($app)));
        $app->scoped(ConversionCoordinator::class, static fn (Application $app): ConversionCoordinator => new ConversionCoordinator(connection: $app->make(DatabaseManager::class)->connection(), blobs: $app->make(MediaBlobs::class), profiles: $app->make(ConversionProfiles::class), operations: $app->make(MediaOperationStore::class), options: $app->make(MediaOptions::class), clock: $app->make(ClockInterface::class), exceptions: $app->make(ExceptionHandler::class)));
        $app->scoped(MediaAttachments::class, static fn (Application $app): SqlMediaAttachments => new SqlMediaAttachments(connection: $app->make(DatabaseManager::class)->connection(), policy: $app->make(MediaPolicy::class), clock: $app->make(ClockInterface::class), blobs: $app->make(MediaBlobs::class), operations: $app->make(MediaOperationStore::class), records: $app->make(DataScopeAuthorizer::class)));
        $app->alias(MediaLibrary::class, ConversionOperations::class);
        $app->scoped(MediaLibrary::class, static fn (Application $app): SqlMediaLibrary => new SqlMediaLibrary(connection: $app->make(DatabaseManager::class)->connection(), blobs: $app->make(MediaBlobs::class), policy: $app->make(MediaPolicy::class), operations: $app->make(MediaOperationStore::class), conversions: $app->make(ConversionCoordinator::class), options: $app->make(MediaOptions::class), clock: $app->make(ClockInterface::class)));
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
