<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProjectedValues
{
    /** @param array<string,string|int|bool|null> $values */
    public function __construct(public array $values)
    {
        ProjectionInput::scalarMap($values);
    }

    public function fieldWitness(string $field): string
    {
        return hash('sha256', json_encode([
            'corex.search.field.v1', $field, array_key_exists($field, $this->values), $this->values[$field] ?? null,
        ], JSON_THROW_ON_ERROR));
    }
}
