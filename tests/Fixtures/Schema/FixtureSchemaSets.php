<?php

declare(strict_types=1);

namespace CoreX\Tests\Fixtures\Schema;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;

final readonly class FixtureSchemaSets implements SchemaSetProvider
{
    /** @param list<SchemaSet> $sets */
    public function __construct(private array $declared) {}

    public function sets(): array
    {
        return $this->declared;
    }
}
