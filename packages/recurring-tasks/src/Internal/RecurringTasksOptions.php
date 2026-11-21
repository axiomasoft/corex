<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider, registrars and runtime checks. */
#[Internal]
final readonly class RecurringTasksOptions
{
    public function __construct(
        public bool $wakeupEnabled,
        public string $wakeupHandlerKind,
        public string $wakeupHandlerVersion,
        public string $wakeupHandler,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $wakeup = is_array($config['wakeup'] ?? null) ? $config['wakeup'] : [];

        $wakeupEnabled = self::bool(violations: $violations, path: 'wakeup.enabled', value: $wakeup['enabled'] ?? false);
        $handlerKind = self::nonEmpty(violations: $violations, path: 'wakeup.handler_kind', value: $wakeup['handler_kind'] ?? 'task-recurrence.materialize');
        $handlerVersion = self::nonEmpty(violations: $violations, path: 'wakeup.handler_version', value: $wakeup['handler_version'] ?? 'task-materialization/1');
        $handler = self::nonEmpty(violations: $violations, path: 'wakeup.handler', value: $wakeup['handler'] ?? MaterializationWakeupHandler::class);

        $violations->failIfAny();

        return new self(wakeupEnabled: $wakeupEnabled, wakeupHandlerKind: $handlerKind, wakeupHandlerVersion: $handlerVersion, wakeupHandler: $handler);
    }

    /** Versioned durable registration of the materialization handler (D96 §C.9). */
    public function wakeupHandlerDefinition(): WakeupHandlerDefinition
    {
        return new WakeupHandlerDefinition(kind: $this->wakeupHandlerKind, version: $this->wakeupHandlerVersion, handler: $this->wakeupHandler);
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

        return 'invalid';
    }
}
