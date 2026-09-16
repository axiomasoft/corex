<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Data\ConversionProfile;

final class ConversionProfiles
{
    /** @var array<string, ConversionDriver> */
    private array $drivers = [];

    /** @param iterable<ConversionDriver> $drivers */
    public function __construct(iterable $drivers)
    {
        foreach ($drivers as $driver) {
            $key = $driver->profile()->key;

            if (isset($this->drivers[$key])) {
                throw new MediaOperationConflict("Duplicate conversion profile [{$key}].");
            }
            $this->drivers[$key] = $driver;
        }
    }

    public function driver(string $profile): ?ConversionDriver
    {
        return $this->drivers[$profile] ?? null;
    }

    public function digest(ConversionDriver $driver): string
    {
        return $driver->profile()->digest($driver::class);
    }

    public function profile(string $profile): ?ConversionProfile
    {
        return $this->driver($profile)?->profile();
    }
}
