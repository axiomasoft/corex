<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

final readonly class AddressQuery
{
    public function __construct(
        public string $text,
        public string $locale,
        public ?string $country,
        public string $consentReference,
    ) {}
}
