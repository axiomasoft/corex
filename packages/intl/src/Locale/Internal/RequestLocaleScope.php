<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Internal;

use CoreX\Intl\Locale\Data\LocaleResolution;

final class RequestLocaleScope
{
    /** @var list<LocaleResolution|null> */
    private array $stack = [];

    public function push(LocaleResolution $resolution): void
    {
        $this->stack[] = $resolution;
    }

    public function current(): ?LocaleResolution
    {
        if ($this->stack === []) {
            return null;
        }

        return $this->stack[array_key_last($this->stack)];
    }

    public function run(LocaleResolution $resolution, callable $callback): mixed
    {
        $this->push(resolution: $resolution);

        try {
            return $callback();
        } finally {
            $this->pop();
        }
    }

    public function pop(): void
    {
        array_pop($this->stack);
    }

    public function clear(): void
    {
        $this->stack = [];
    }
}
