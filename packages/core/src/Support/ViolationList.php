<?php

declare(strict_types=1);

namespace CoreX\Support;

final class ViolationList
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
