<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use InvalidArgumentException;

final readonly class PanelAccess
{
    /** @param list<mixed> $actions */
    public function __construct(public bool $view, public array $actions, public ?string $reason)
    {
        if ((! $view && $actions !== [])
            || count($actions) !== count(array_unique($actions))
            || array_filter($actions, static fn ($action): bool => ! is_string($action) || $action === '') !== []) {
            throw new InvalidArgumentException('Panel access actions must be a unique string set.');
        }
    }

    public static function deny(string $reason): self
    {
        return new self(view: false, actions: [], reason: $reason);
    }
}
