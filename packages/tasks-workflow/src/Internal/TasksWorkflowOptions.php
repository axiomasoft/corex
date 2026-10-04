<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider, registrars and runtime checks. */
final readonly class TasksWorkflowOptions
{
    public function __construct(
        public bool $wakeupEnabled,
        public string $wakeupHandlerKind,
        public string $wakeupHandlerVersion,
        public string $wakeupHandler,
        public bool $wakeupAcceptLegacyUnstamped,
        public bool $completionCaptureEnabled,
        public string $completionCaptureDefinitionRef,
        public string $completionCaptureVersionRef,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $wakeup = is_array($config['wakeup'] ?? null) ? $config['wakeup'] : [];
        $capture = is_array($config['completion_capture'] ?? null) ? $config['completion_capture'] : [];

        $wakeupEnabled = self::bool($violations, 'wakeup.enabled', $wakeup['enabled'] ?? false);
        $handlerKind = self::nonEmpty($violations, 'wakeup.handler_kind', $wakeup['handler_kind'] ?? 'task-recurrence.materialize');
        $handlerVersion = self::nonEmpty($violations, 'wakeup.handler_version', $wakeup['handler_version'] ?? 'task-materialization/1');
        $handler = self::nonEmpty($violations, 'wakeup.handler', $wakeup['handler'] ?? 'CoreX\\TasksWorkflow\\Internal\\MaterializationWakeupHandler');
        $acceptLegacy = self::bool($violations, 'wakeup.accept_legacy_unstamped', $wakeup['accept_legacy_unstamped'] ?? false);
        $captureEnabled = self::bool($violations, 'completion_capture.enabled', $capture['enabled'] ?? false);
        $definitionRef = self::nonEmpty($violations, 'completion_capture.definition_ref', $capture['definition_ref'] ?? 'task-completion/1');
        $versionRef = self::nonEmpty($violations, 'completion_capture.version_ref', $capture['version_ref'] ?? 'task-completion/1');

        $violations->failIfAny();

        return new self($wakeupEnabled, $handlerKind, $handlerVersion, $handler, $acceptLegacy, $captureEnabled, $definitionRef, $versionRef);
    }

    /** @return list<string> config paths of enabled dangerous modes */
    public function dangerousModes(): array
    {
        return $this->wakeupAcceptLegacyUnstamped ? ['corex-tasks-workflow.wakeup.accept_legacy_unstamped'] : [];
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }
}
