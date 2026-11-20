<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Context\Enums\ProviderObservationKind;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProviderObservation
{
    public function __construct(public ProviderObservationKind $kind, public string $reference, public string $observer)
    {
        if ($reference === '' || strlen($reference) > 255 || $observer === '' || strlen($observer) > 191 || preg_match('/[\x00-\x1f\x7f]/', $reference.$observer) === 1) {
            throw new InvalidArgumentException('Provider observation requires evidence and an observer.');
        }
    }
}
