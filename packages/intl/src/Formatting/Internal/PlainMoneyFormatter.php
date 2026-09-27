<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal\Money;

use Brick\Money\Money;
use CoreX\Intl\Contracts\MoneyFormatter;
use CoreX\Intl\Data\FormatPolicy;
use CoreX\Intl\Data\FormattedMoney;
use CoreX\Intl\Data\LocaleResolution;
use CoreX\Intl\Exceptions\UnsupportedFormattingException;
use Locale;
use NumberFormatter;
use Override;
use Throwable;

final class PlainMoneyFormatter implements MoneyFormatter
{
    public function __construct(
        private readonly bool $icuEnabled,
    ) {}

    #[Override]
    public function format(Money $amount, LocaleResolution $locale, FormatPolicy $policy): FormattedMoney
    {
        $exactDecimal = (string) $amount->getAmount();
        $currency = $amount->getCurrency()->getCurrencyCode();

        if ($policy->mode === 'icuVerified' && $this->icuEnabled && class_exists(NumberFormatter::class)) {
            try {
                $formatter = new NumberFormatter($locale->used, NumberFormatter::CURRENCY);
                $formatted = $formatter->formatCurrency((float) $exactDecimal, $currency);

                if (is_string($formatted) && $formatted !== '') {
                    return new FormattedMoney(
                        text: $formatted,
                        exactDecimal: $exactDecimal,
                        currency: $currency,
                        locale: $locale->used,
                        method: 'icuVerified',
                        wasFallback: false,
                        icuVersion: defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : null,
                    );
                }
            } catch (Throwable) {
                // fall through to plain exact
            }
        }

        if ($policy->mode === 'icuVerified' && ! $this->icuEnabled) {
            throw new UnsupportedFormattingException('ICU formatting is unavailable in the current environment.');
        }

        $text = $currency.' '.$exactDecimal;

        return new FormattedMoney(
            text: $text,
            exactDecimal: $exactDecimal,
            currency: $currency,
            locale: $locale->used,
            method: 'plainExact',
            wasFallback: $policy->mode === 'icuVerified',
            icuVersion: class_exists(Locale::class) && defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : null,
        );
    }
}
