<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Intl\Currency\Contracts\CurrencyConverter;
use CoreX\Intl\Currency\Contracts\CurrencyMetadata;
use CoreX\Intl\Currency\Contracts\RateImporter;
use CoreX\Intl\Currency\Contracts\RateProvider;
use CoreX\Intl\Currency\Internal\BrickCurrencyConverter;
use CoreX\Intl\Currency\Internal\BrickCurrencyMetadata;
use CoreX\Intl\Currency\Internal\SqlRateImporter;
use CoreX\Intl\Currency\Internal\SqlRateProvider;
use CoreX\Intl\Errors\Internal\PackageProblemDefinitions;
use CoreX\Intl\Formatting\Contracts\MoneyFormatter;
use CoreX\Intl\Formatting\Internal\PlainMoneyFormatter;
use CoreX\Intl\Internal\IntlConfigSection;
use CoreX\Intl\Internal\IntlOptions;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Internal\Bcp47Validator;
use CoreX\Intl\Locale\Internal\DefaultLocaleResolver;
use CoreX\Intl\Locale\Internal\LocalePolicyBuilder;
use CoreX\Intl\Locale\Internal\RequestLocaleScope;
use CoreX\Intl\Locale\Laravel\LocalePortBindings;
use CoreX\Intl\Money\Laravel\MoneyCast;
use CoreX\Intl\Territory\Contracts\DatasetImporter;
use CoreX\Intl\Territory\Contracts\GeographyProvider;
use CoreX\Intl\Territory\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Intl\Territory\Internal\Reference\ReferenceCursorCodec;
use CoreX\Intl\Territory\Internal\Reference\SqlDatasetImporter;
use CoreX\Intl\Territory\Internal\Reference\SqlGeographyProvider;
use CoreX\Intl\Translation\Contracts\TranslationReader;
use CoreX\Intl\Translation\Internal\LaravelTranslationReader;
use CoreX\Modules\Contracts\FieldCastRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        LocalePortBindings::register($this->app);

        $this->app->tag([IntlConfigSection::class], ConfigReportSection::class);

        $this->callAfterResolving(FieldCastRegistry::class, static function (FieldCastRegistry $casts): void {
            $casts->register(MoneyCast::class, 'money');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/corex-intl.php', 'corex-intl');
        $this->app->singleton(IntlOptions::class, static fn (Application $app): IntlOptions => IntlOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-intl', []),
        ));

        $this->app->scoped(LocalePolicy::class, function (Application $app): LocalePolicy {
            return (new LocalePolicyBuilder)->build(config: $app->make(IntlOptions::class)->localePolicyConfig());
        });

        $this->app->scoped(Bcp47Validator::class, function (Application $app): Bcp47Validator {
            $policy = $app->make(LocalePolicy::class);

            return new Bcp47Validator(
                supportedTags: $policy->supportedTags,
                maxTagLength: $app->make(IntlOptions::class)->localeMaxTagLength,
            );
        });

        $this->app->scoped(LocaleResolver::class, DefaultLocaleResolver::class);
        $this->app->scoped(TranslationReader::class, LaravelTranslationReader::class);
        $this->app->scoped(RequestLocaleScope::class);

        $this->app->scoped(CurrencyMetadata::class, function (Application $app): CurrencyMetadata {
            return new BrickCurrencyMetadata(
                metadataVersion: $app->make(IntlOptions::class)->moneyMetadataVersion,
            );
        });

        $this->app->scoped(RateProvider::class, function (Application $app): RateProvider {
            return new SqlRateProvider(connection: $app['db']->connection());
        });

        $this->app->scoped(RateImporter::class, function (Application $app): RateImporter {
            return new SqlRateImporter(
                connection: $app['db']->connection(),
                authorizer: $app->make(DataScopeAuthorizer::class),
                batchLimit: $app->make(IntlOptions::class)->moneyBatchLimit,
            );
        });

        $this->app->scoped(CurrencyConverter::class, BrickCurrencyConverter::class);

        $this->app->scoped(MoneyFormatter::class, function (Application $app): MoneyFormatter {
            return new PlainMoneyFormatter(
                icuEnabled: $app->make(IntlOptions::class)->formattingIcuEnabled,
            );
        });

        $this->app->scoped(FixtureReferenceNormalizer::class);
        $this->app->scoped(ReferenceCursorCodec::class, function (Application $app): ReferenceCursorCodec {
            return new ReferenceCursorCodec(secret: $app->make(IntlOptions::class)->referenceCursorSecret->reveal());
        });

        $this->app->scoped(GeographyProvider::class, static fn (Application $app): SqlGeographyProvider => new SqlGeographyProvider(
            connection: $app->make(Connection::class),
            datasetKey: $app->make(IntlOptions::class)->referenceDatasetKey,
            normalizer: $app->make(FixtureReferenceNormalizer::class),
            cursors: $app->make(ReferenceCursorCodec::class),
        ));
        $this->app->scoped(DatasetImporter::class, static fn (Application $app): SqlDatasetImporter => new SqlDatasetImporter(
            connection: $app->make(Connection::class),
            datasetKey: $app->make(IntlOptions::class)->referenceDatasetKey,
            normalizer: $app->make(FixtureReferenceNormalizer::class),
            authorizer: $app->make(DataScopeAuthorizer::class),
        ));
    }

    public function boot(): void
    {
        $this->app->make(IntlOptions::class);

        $this->publishes([
            __DIR__.'/../config/corex-intl.php' => config_path('corex-intl.php'),
        ], 'corex-intl-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
