<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class DocumentRevision
{
    public function __construct(public string $value)
    {
        if ($value !== 'absent' && preg_match('/\A[0-9a-f]{64}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid DocumentRevision.');
        }
    }

    public static function absent(): self
    {
        return new self('absent');
    }
}
