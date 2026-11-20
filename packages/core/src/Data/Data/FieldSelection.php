<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldSelection
{
    /** @param list<string> $fields */
    public function __construct(public array $fields)
    {
        if (count($fields) > 256) {
            throw new InvalidArgumentException('Invalid field selection.');
        }
        $seen = [];
        foreach ($fields as $index => $id) {
            if (count($seen) !== $index || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}\z/D', $id) !== 1 || isset($seen[$id])) {
                throw new InvalidArgumentException('A selection requires unique stable field identifiers.');
            }
            $seen[$id] = true;
        }
    }
}
