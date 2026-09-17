<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal\Money;

use Brick\Money\Currency;
use CoreX\Intl\Contracts\CurrencyMetadata;
use CoreX\Intl\Data\CurrencyInfo;
use CoreX\Intl\Exceptions\InvalidRateException;
use Override;
use Throwable;

final class BrickCurrencyMetadata implements CurrencyMetadata
{
    public function __construct(
        private readonly string $metadataVersion,
    ) {}

    #[Override]
    public function get(string $currencyCode): CurrencyInfo
    {
        $code = strtoupper(trim($currencyCode));

        if ($code === '' || strlen($code) !== 3) {
            throw new InvalidRateException('Currency code must be a three-letter ISO code.');
        }

        try {
            $currency = Currency::of($code);
        } catch (Throwable) {
            throw new InvalidRateException("Unknown currency code [{$code}].");
        }

        $hash = hash('sha256', $this->metadataVersion."\0".$code."\0".$currency->getDefaultFractionDigits());

        return new CurrencyInfo(
            isoCode: $code,
            exponent: $currency->getDefaultFractionDigits(),
            name: null,
            symbol: null,
            metadataVersion: $this->metadataVersion,
            metadataHash: $hash,
        );
    }
}
