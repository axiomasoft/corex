<?php

declare(strict_types=1);

namespace CoreX\Intl\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Intl\Internal\IntlOptions;
use CoreX\Intl\Territory\Contracts\DatasetImporter;
use CoreX\Intl\Territory\Contracts\GeographyProvider;
use CoreX\Intl\Territory\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Intl\Territory\Internal\Reference\ReferenceCursorCodec;
use CoreX\Intl\Territory\Internal\Reference\SqlDatasetImporter;
use CoreX\Intl\Territory\Internal\Reference\SqlGeographyProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlReferenceRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(FixtureReferenceNormalizer::class);
        $app->scoped(ReferenceCursorCodec::class, static fn (Application $app): ReferenceCursorCodec => new ReferenceCursorCodec(
            secret: $app->make(IntlOptions::class)->referenceCursorSecret->reveal(),
        ));
        $app->scoped(GeographyProvider::class, static fn (Application $app): SqlGeographyProvider => new SqlGeographyProvider(
            connection: $app->make(Connection::class),
            datasetKey: $app->make(IntlOptions::class)->referenceDatasetKey,
            normalizer: $app->make(FixtureReferenceNormalizer::class),
            cursors: $app->make(ReferenceCursorCodec::class),
        ));
        $app->scoped(DatasetImporter::class, static fn (Application $app): SqlDatasetImporter => new SqlDatasetImporter(
            connection: $app->make(Connection::class),
            datasetKey: $app->make(IntlOptions::class)->referenceDatasetKey,
            normalizer: $app->make(FixtureReferenceNormalizer::class),
            authorizer: $app->make(DataScopeAuthorizer::class),
            clock: $app->make(ClockInterface::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void {}
}
