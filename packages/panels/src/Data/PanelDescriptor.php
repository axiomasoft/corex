<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use InvalidArgumentException;

final readonly class PanelDescriptor
{
    /** @param list<mixed> $supportedActions */
    public function __construct(
        public string $version,
        public string $key,
        public string $owningModule,
        public ?string $requiredCapability,
        public array $supportedActions,
        public string $label,
    ) {
        if ($version !== 'panel/1' || ! self::validKey($key) || ! self::validKey($owningModule) || ! self::validKey($label)) {
            throw new InvalidArgumentException('Invalid panel descriptor.');
        }

        if ($requiredCapability !== null && ! self::validKey($requiredCapability)) {
            throw new InvalidArgumentException('Invalid required panel capability.');
        }

        if (count($supportedActions) !== count(array_unique($supportedActions)) || array_filter($supportedActions, static fn ($action): bool => ! is_string($action) || ! self::validKey($action)) !== []) {
            throw new InvalidArgumentException('Panel actions must be unique registered keys.');
        }
    }

    private static function validKey(string $value): bool
    {
        return $value !== '' && strlen($value) <= 128;
    }
}
