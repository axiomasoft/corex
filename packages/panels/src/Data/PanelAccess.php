<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class PanelAccess
{
    /** @param list<string> $actions */
    public function __construct(public bool $view, public array $actions, public ?string $reason)
    {
        self::validateInput(view: $view, actions: $actions, reason: $reason);
    }

    public static function deny(string $reason): self
    {
        return new self(view: false, actions: [], reason: $reason);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $actions
     */
    private static function validateInput(/** Runtime input. */
        bool $view, /** Runtime input. */
        array $actions, /** Runtime input. */
        ?string $reason): void
    {
        foreach ($actions as $action) {
            if (! is_string($action)) {
                throw new InvalidArgumentException('Panel actions must be strings.');
            }
        }

        if ((! $view && $actions !== [])
            || count($actions) !== count(array_unique($actions))
            || array_filter($actions, static fn ($action): bool => $action === '') !== []) {
            throw new InvalidArgumentException('Panel access actions must be a unique string set.');
        }
    }
}
