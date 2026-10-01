<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
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
