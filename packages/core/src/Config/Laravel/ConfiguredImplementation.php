<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Foundation\Application;

/** Resolves the implementation selected by configuration and enforces the contract it was selected for. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ConfiguredImplementation
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @param  class-string<T>  $implementation
     * @return T
     */
    public static function resolve(Application $app, string $contract, string $implementation): object
    {
        $resolved = $app->make($implementation);

        if (! $resolved instanceof $contract) {
            throw new InvalidConfiguration([$implementation.': must implement '.$contract]);
        }

        return $resolved;
    }
}
