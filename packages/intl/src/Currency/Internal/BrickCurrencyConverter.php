<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Internal;

use Brick\Money\Money;
use CoreX\Intl\Currency\Contracts\CurrencyConverter;
use CoreX\Intl\Currency\Data\ConversionPolicy;
use CoreX\Intl\Currency\Data\ConversionReceipt;
use CoreX\Intl\Currency\Data\RateSnapshot;
use CoreX\Intl\Currency\Exceptions\InvalidRateException;
use Override;

final class BrickCurrencyConverter implements CurrencyConverter
{
    #[Override]
    public function convert(Money $amount, RateSnapshot $rate, ConversionPolicy $policy): ConversionReceipt
    {
        $sourceCode = $amount->getCurrency()->getCurrencyCode();

        if ($sourceCode === $rate->quote && $rate->base === $sourceCode && $rate->rateDecimal === '1') {
            return new ConversionReceipt(
                source: $amount,
                result: $amount,
                rateSnapshotId: $rate->id,
                rateHash: RateSnapshotMapper::payloadHash(snapshot: $rate),
                policyHash: $this->policyHash(policy: $policy),
                requestedDate: $rate->validOn,
            );
        }

        if ($sourceCode === $rate->base && $rate->quote === $rate->base) {
            return new ConversionReceipt(
                source: $amount,
                result: $amount,
                rateSnapshotId: $rate->id,
                rateHash: RateSnapshotMapper::payloadHash(snapshot: $rate),
                policyHash: $this->policyHash(policy: $policy),
                requestedDate: $rate->validOn,
            );
        }

        if ($sourceCode !== $rate->base) {
            throw new InvalidRateException('Conversion amount currency does not match the rate snapshot base.');
        }

        $converted = $amount->convertedTo(
            currency: $rate->quote,
            exchangeRate: $rate->rateDecimal,
            context: $policy->targetContext,
            roundingMode: $policy->roundingMode,
        );

        return new ConversionReceipt(
            source: $amount,
            result: $converted,
            rateSnapshotId: $rate->id,
            rateHash: RateSnapshotMapper::payloadHash(snapshot: $rate),
            policyHash: $this->policyHash(policy: $policy),
            requestedDate: $rate->validOn,
        );
    }

    private function policyHash(ConversionPolicy $policy): string
    {
        return hash('sha256', $policy->policyId."\0".$policy->roundingMode->name);
    }
}
