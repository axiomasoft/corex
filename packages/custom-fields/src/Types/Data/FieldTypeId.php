<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldTypeId
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)*\z/D', $value) !== 1 || strlen($value) > 128) {
            throw new InvalidArgumentException('Invalid FieldTypeId.');
        }
    }
}
