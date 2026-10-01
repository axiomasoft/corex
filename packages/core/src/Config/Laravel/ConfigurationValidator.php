<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Exceptions\InvalidConfiguration;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ConfigurationValidator
{
    /** @var list<string> */
    private array $items = [];

    public function add(string $path, string $message): void
    {
        $this->items[] = $path.': '.$message;
    }

    public function failIfAny(): void
    {
        if ($this->items === []) {
            return;
        }

        throw new InvalidConfiguration($this->items);
    }
}
