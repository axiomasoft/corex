<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AcceptNegotiation
{
    public const string JSON = 'application/json';

    public const string PROBLEM = 'application/problem+json';

    public static function select(string $header): ?string
    {
        if (trim($header) === '') {
            return self::JSON;
        }

        $best = null;
        $bestRank = -1.0;

        foreach (self::ranges($header) as [$type, $quality]) {
            foreach ([self::JSON, self::PROBLEM] as $candidate) {
                if ($quality <= 0 || ! self::matches($type, $candidate)) {
                    continue;
                }

                $rank = ($quality * 1000) + self::specificity($type) + ($candidate === self::JSON ? 0.1 : 0.0);

                if ($rank > $bestRank) {
                    $bestRank = $rank;
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    private static function ranges(string $header): array
    {
        $ranges = [];

        foreach (explode(',', $header) as $part) {
            $pieces = array_map(trim(...), explode(';', $part));
            $type = strtolower($pieces[0]);

            if ($type === '') {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (! str_starts_with($parameter, 'q=')) {
                    continue;
                }

                $value = substr($parameter, 2);

                if (preg_match('/\A(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)\z/', $value) !== 1) {
                    $quality = 0.0;

                    break;
                }

                $quality = (float) $value;
            }

            $ranges[] = [$type, $quality];
        }

        return $ranges;
    }

    private static function matches(string $range, string $candidate): bool
    {
        if ($range === '*/*' || $range === $candidate) {
            return true;
        }

        if (! str_ends_with($range, '/*')) {
            return false;
        }

        return str_starts_with($candidate, substr($range, 0, -1));
    }

    private static function specificity(string $range): float
    {
        if ($range === '*/*') {
            return 0.0;
        }

        return str_ends_with($range, '/*') ? 1.0 : 2.0;
    }
}
