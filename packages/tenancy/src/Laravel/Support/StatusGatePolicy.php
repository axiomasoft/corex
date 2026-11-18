<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class StatusGatePolicy
{
    /**
     * @param  list<string>  $readOnlyMethods
     */
    public function __construct(
        public ?string $paymentRoute,
        public ?string $exportRoute,
        public array $readOnlyMethods,
    ) {}

    public function isReadOnlyMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->readOnlyMethods, true);
    }
}
