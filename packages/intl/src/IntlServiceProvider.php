<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Intl\Contracts\CurrencyConverter;
use CoreX\Intl\Contracts\CurrencyMetadata;
use CoreX\Intl\Contracts\DatasetImporter;
use CoreX\Intl\Contracts\GeographyProvider;
use CoreX\Intl\Contracts\LocaleResolver;
use CoreX\Intl\Contracts\MoneyFormatter;
use CoreX\Intl\Contracts\RateImporter;
use CoreX\Intl\Contracts\RateProvider;
use CoreX\Intl\Contracts\TranslationReader;
use CoreX\Intl\Data\LocalePolicy;
use CoreX\Intl\Internal\Locale\Bcp47Validator;
use CoreX\Intl\Internal\Locale\DefaultLocaleResolver;
use CoreX\Intl\Internal\Locale\LaravelTranslationReader;
use CoreX\Intl\Internal\Locale\LocalePolicyBuilder;
use CoreX\Intl\Internal\Locale\RequestLocaleScope;
use CoreX\Intl\Internal\Money\BrickCurrencyConverter;
use CoreX\Intl\Internal\Money\BrickCurrencyMetadata;
use CoreX\Intl\Internal\Money\PlainMoneyFormatter;
use CoreX\Intl\Internal\Money\SqlRateImporter;
use CoreX\Intl\Internal\Money\SqlRateProvider;
use CoreX\Intl\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Intl\Internal\Reference\ReferenceCursorCodec;
use CoreX\Intl\Internal\Reference\SqlDatasetImporter;
use CoreX\Intl\Internal\Reference\SqlGeographyProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class IntlServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-intl.php', 'corex-intl');

        $this->app->scoped(LocalePolicy::class, function (Application $app): LocalePolicy {
            return (new LocalePolicyBuilder)->build(config: (array) $app['config']->get('corex-intl.locale', []));
        });

        $this->app->scoped(Bcp47Validator::class, function (Application $app): Bcp47Validator {
            $policy = $app->make(LocalePolicy::class);

            return new Bcp47Validator(
                supportedTags: $policy->supportedTags,
                maxTagLength: (int) $app['config']->get('corex-intl.locale.max_tag_length', 128),
            );
        });

        $this->app->scoped(LocaleResolver::class, DefaultLocaleResolver::class);
        $this->app->scoped(TranslationReader::class, LaravelTranslationReader::class);
        $this->app->scoped(RequestLocaleScope::class);

        $this->app->scoped(CurrencyMetadata::class, function (Application $app): CurrencyMetadata {
            return new BrickCurrencyMetadata(
                metadataVersion: (string) $app['config']->get('corex-intl.money.metadata_version', 'brick/1'),
            );
        });

        $this->app->scoped(RateProvider::class, function (Application $app): RateProvider {
            return new SqlRateProvider(connection: $app['db']->connection());
        });

        $this->app->scoped(RateImporter::class, function (Application $app): RateImporter {
            return new SqlRateImporter(
                connection: $app['db']->connection(),
                authorizer: $app->make(DataScopeAuthorizer::class),
                batchLimit: (int) $app['config']->get('corex-intl.money.batch_limit', 1000),
            );
        });

        $this->app->scoped(CurrencyConverter::class, BrickCurrencyConverter::class);

        $this->app->scoped(MoneyFormatter::class, function (Application $app): MoneyFormatter {
            return new PlainMoneyFormatter(
                icuEnabled: (bool) $app['config']->get('corex-intl.formatting.icu_enabled', false),
            );
        });

        $this->app->scoped(FixtureReferenceNormalizer::class);
        $this->app->scoped(ReferenceCursorCodec::class, function (Application $app): ReferenceCursorCodec {
            return new ReferenceCursorCodec(secret: (string) $app['config']->get('corex-intl.reference.cursor_secret'));
        });

        $this->app->scoped(GeographyProvider::class, SqlGeographyProvider::class);
        $this->app->scoped(DatasetImporter::class, SqlDatasetImporter::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-intl.php' => config_path('corex-intl.php'),
        ], 'corex-intl-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
