<?php

declare(strict_types=1);

namespace CoreX\Intl\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Intl\Currency\Contracts\CurrencyConverter;
use CoreX\Intl\Currency\Contracts\CurrencyMetadata;
use CoreX\Intl\Currency\Contracts\RateImporter;
use CoreX\Intl\Currency\Contracts\RateProvider;
use CoreX\Intl\Currency\Internal\BrickCurrencyConverter;
use CoreX\Intl\Currency\Internal\BrickCurrencyMetadata;
use CoreX\Intl\Currency\Internal\SqlRateImporter;
use CoreX\Intl\Currency\Internal\SqlRateProvider;
use CoreX\Intl\Formatting\Contracts\MoneyFormatter;
use CoreX\Intl\Formatting\Internal\PlainMoneyFormatter;
use CoreX\Intl\Internal\IntlModuleReferences;
use CoreX\Intl\Internal\IntlOptions;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Internal\Bcp47Validator;
use CoreX\Intl\Locale\Internal\DefaultLocaleResolver;
use CoreX\Intl\Locale\Internal\LocalePolicyBuilder;
use CoreX\Intl\Locale\Internal\RequestLocaleScope;
use CoreX\Intl\Locale\Laravel\LocalePortBindings;
use CoreX\Intl\Translation\Contracts\TranslationReader;
use CoreX\Intl\Translation\Internal\LaravelTranslationReader;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(IntlModuleReferences::class);
        $app->tag(IntlModuleReferences::class, ModuleReferences::class);

        LocalePortBindings::register($app);

        $app->singleton(IntlOptions::class, static fn (Application $app): IntlOptions => IntlOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-intl', []),
        ));
        $app->scoped(LocalePolicy::class, static fn (Application $app): LocalePolicy => (new LocalePolicyBuilder)->build(config: $app->make(IntlOptions::class)->localePolicyConfig()));
        $app->scoped(Bcp47Validator::class, static fn (Application $app): Bcp47Validator => new Bcp47Validator(
            supportedTags: $app->make(LocalePolicy::class)->supportedTags,
            maxTagLength: $app->make(IntlOptions::class)->localeMaxTagLength,
        ));
        $app->scoped(LocaleResolver::class, DefaultLocaleResolver::class);
        $app->scoped(TranslationReader::class, LaravelTranslationReader::class);
        $app->scoped(RequestLocaleScope::class);
        $app->scoped(CurrencyMetadata::class, static fn (Application $app): CurrencyMetadata => new BrickCurrencyMetadata(
            metadataVersion: $app->make(IntlOptions::class)->moneyMetadataVersion,
        ));
        $app->scoped(RateProvider::class, static fn (Application $app): RateProvider => new SqlRateProvider(connection: $app->make(DatabaseManager::class)->connection()));
        $app->scoped(RateImporter::class, static fn (Application $app): RateImporter => new SqlRateImporter(
            connection: $app->make(DatabaseManager::class)->connection(),
            authorizer: $app->make(DataScopeAuthorizer::class),
            current: $app->make(CurrentWorkScope::class),
            clock: $app->make(ClockInterface::class),
            batchLimit: $app->make(IntlOptions::class)->moneyBatchLimit,
        ));
        $app->scoped(CurrencyConverter::class, BrickCurrencyConverter::class);
        $app->scoped(MoneyFormatter::class, static fn (Application $app): MoneyFormatter => new PlainMoneyFormatter(
            icuEnabled: $app->make(IntlOptions::class)->formattingIcuEnabled,
        ));
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(IntlOptions::class);
    }
}
