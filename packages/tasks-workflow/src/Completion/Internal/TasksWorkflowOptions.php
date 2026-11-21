<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

#[Internal]
final readonly class TasksWorkflowOptions
{
    private function __construct(
        public bool $enabled,
        public ?string $definitionRef,
        public ?string $versionRef,
    ) {}

    /** @param array<array-key, mixed> $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $enabled = array_key_exists('enabled', $config) ? $config['enabled'] : false;

        if (! is_bool($enabled)) {
            $violations->add(path: 'enabled', message: 'must be a boolean');
            $enabled = false;
        }

        $definitionRef = self::stableRef($violations, 'definition_ref', $config['definition_ref'] ?? null, $enabled);
        $versionRef = self::stableRef($violations, 'version_ref', $config['version_ref'] ?? null, $enabled);
        $violations->failIfAny();

        return new self($enabled, $definitionRef, $versionRef);
    }

    private static function stableRef(ConfigurationValidator $violations, string $path, mixed $value, bool $required): ?string
    {
        if ($value === null && ! $required) {
            return null;
        }

        if (is_string($value) && $value !== '' && trim($value) === $value) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty stable reference');

        return null;
    }
}
