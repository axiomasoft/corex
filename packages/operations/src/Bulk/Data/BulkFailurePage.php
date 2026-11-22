<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BulkFailurePage
{
    /** @param list<array{ordinal: string, code: string, path: ?string}> $records */
    public function __construct(public array $records, public ?string $next)
    {
        if (count($records) > 100 || ($next !== null && ! self::ordinal($next))) {
            throw new InvalidArgumentException('Invalid bulk failure page.');
        }

        $previous = null;

        foreach ($records as $row) {
            if (! self::ordinal($row['ordinal']) || ($previous !== null && self::compare(left: $previous, right: $row['ordinal']) >= 0)
                || preg_match('/\A[a-zA-Z][a-zA-Z0-9_.-]{0,127}\z/D', $row['code']) !== 1
                || ($row['path'] !== null && (strlen($row['path']) > 512 || preg_match('/\A(?:\/(?:[^~\x00-\x1f\x7f]|~[01])*)*\z/Du', $row['path']) !== 1))) {
                throw new InvalidArgumentException('Invalid bulk failure row.');
            }

            $previous = $row['ordinal'];
        }

        if ($next !== null && $next !== $previous) {
            throw new InvalidArgumentException('Invalid bulk failure cursor.');
        }
    }

    private static function compare(string $left, string $right): int
    {
        $length = strlen($left) <=> strlen($right);

        return $length === 0 ? strcmp($left, $right) : $length;
    }

    private static function ordinal(string $value): bool
    {
        return preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/D', $value) === 1;
    }
}
