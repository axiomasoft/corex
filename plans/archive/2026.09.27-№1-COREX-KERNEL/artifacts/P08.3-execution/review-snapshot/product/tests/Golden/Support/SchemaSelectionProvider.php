<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;

/** Driver projection of the actual declarations; it never changes a set definition. */
final readonly class SchemaSelectionProvider implements SchemaSetProvider
{
    /** @param list<SchemaSet> $selection */
    public function __construct(private array $selection) {}

    public function sets(): array
    {
        return $this->selection;
    }
}
