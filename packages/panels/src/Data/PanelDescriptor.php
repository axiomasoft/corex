<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class PanelDescriptor
{
    /** @param list<string> $supportedActions */
    public function __construct(public string $version, public string $key, public string $owningModule, public ?string $requiredCapability, public array $supportedActions, public string $label)
    {
        self::validateInput(version: $version, key: $key, owningModule: $owningModule, requiredCapability: $requiredCapability, supportedActions: $supportedActions, label: $label);
    }

    private static function validKey(string $value): bool
    {
        return $value !== '' && strlen($value) <= 128;
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $supportedActions
     */
    private static function validateInput(
        /** Runtime input. */
        string $version,
        /** Runtime input. */
        string $key,
        /** Runtime input. */
        string $owningModule,
        /** Runtime input. */
        ?string $requiredCapability,
        /** Runtime input. */
        array $supportedActions,
        /** Runtime input. */
        string $label,
    ): void {
        foreach ($supportedActions as $action) {
            if (! is_string($action)) {
                throw new InvalidArgumentException('Panel actions must be strings.');
            }
        }

        if ($version !== 'panel/1' || ! self::validKey($key) || ! self::validKey($owningModule) || ! self::validKey($label)) {
            throw new InvalidArgumentException('Invalid panel descriptor.');
        }

        if ($requiredCapability !== null && ! self::validKey($requiredCapability)) {
            throw new InvalidArgumentException('Invalid required panel capability.');
        }

        if (count($supportedActions) !== count(array_unique($supportedActions)) || array_filter($supportedActions, static fn ($action): bool => ! self::validKey($action)) !== []) {
            throw new InvalidArgumentException('Panel actions must be unique registered keys.');
        }
    }
}
