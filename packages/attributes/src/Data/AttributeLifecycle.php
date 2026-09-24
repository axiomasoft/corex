<?php

declare(strict_types=1);

namespace CoreX\Attributes\Data;

use InvalidArgumentException;

enum AttributeLifecycle: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Migrating = 'migrating';
    case Retired = 'retired';

    public function allows(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::Deprecated, self::Migrating],
            self::Deprecated => [self::Migrating, self::Retired],
            self::Migrating => [self::Active, self::Retired],
            self::Retired => [],
        }, true);
    }

    public function assertTransition(self $next): void
    {
        if (! $this->allows($next)) {
            throw new InvalidArgumentException("Attribute lifecycle cannot move from {$this->value} to {$next->value}.");
        }
    }
}
