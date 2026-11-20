<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ScopeIdentity
{
    public static function opaque(string $value): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw new InvalidArgumentException('Invalid scope identifier.');
        }
    }

    public static function principal(string $kind, string $id): void
    {
        self::opaque($id);

        if (! in_array($kind, ['user', 'agent', 'api_key', 'service'], true)) {
            throw new InvalidArgumentException('Invalid principal kind.');
        }
    }

    public static function physical(string $deployment, string $binding, int $generation, string $incarnation, string $epoch): void
    {
        foreach ([$deployment, $binding, $incarnation] as $id) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,127}\z/D', $id) !== 1 || str_contains($id, '://')) {
                throw new InvalidArgumentException('Invalid physical binding identity.');
            }
        }

        if ($generation < 1 || preg_match('/\A[a-f0-9]{64}\z/D', $epoch) !== 1) {
            throw new InvalidArgumentException('Invalid physical binding fence.');
        }
    }
}
