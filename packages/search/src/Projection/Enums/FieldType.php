<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum FieldType: string
{
    case Text = 'text';
    case Boolean = 'boolean';
    case Integer = 'integer';

    public function accepts(string|bool|int|null $value): bool
    {
        return $value === null || match ($this) {
            self::Text => is_string($value),
            self::Boolean => is_bool($value),
            self::Integer => is_int($value),
        };
    }
}
