<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class ScopeOptionsFactory
{
    /** @param array<array-key,mixed> $config */
    public static function fromArray(array $config): ScopeOptions
    {
        return new ScopeOptions(self::name($config['deployment_id'] ?? null), self::name($config['control_connection'] ?? null),
            self::jobs($config['tenant_jobs'] ?? []), self::jobs($config['central_jobs'] ?? []));
    }

    private static function name(mixed $name): ?string
    {
        if ($name !== null && (! is_string($name) || $name === '')) {
            throw new InvalidArgumentException('Invalid scope authority configuration.');
        }

        return $name;
    }

    /** @return list<string> */
    private static function jobs(mixed $list): array
    {
        if (! is_array($list) || ! array_is_list($list)) {
            throw new InvalidArgumentException('Invalid work handler list.');
        }
        foreach ($list as $handler) {
            if (! is_string($handler) || $handler === '') {
                throw new InvalidArgumentException('Invalid work handler.');
            }
        }

        return $list;
    }
}
