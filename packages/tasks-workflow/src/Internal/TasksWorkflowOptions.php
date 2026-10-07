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

        $wakeupEnabled = self::bool(violations: $violations, path: 'wakeup.enabled', value: $wakeup['enabled'] ?? false);
        $handlerKind = self::nonEmpty(violations: $violations, path: 'wakeup.handler_kind', value: $wakeup['handler_kind'] ?? 'task-recurrence.materialize');
        $handlerVersion = self::nonEmpty(violations: $violations, path: 'wakeup.handler_version', value: $wakeup['handler_version'] ?? 'task-materialization/1');
        $handler = self::nonEmpty(violations: $violations, path: 'wakeup.handler', value: $wakeup['handler'] ?? MaterializationWakeupHandler::class);
        $acceptLegacy = self::bool(violations: $violations, path: 'wakeup.accept_legacy_unstamped', value: $wakeup['accept_legacy_unstamped'] ?? false);
        $captureEnabled = self::bool(violations: $violations, path: 'completion_capture.enabled', value: $capture['enabled'] ?? false);
        $definitionRef = self::nonEmpty(violations: $violations, path: 'completion_capture.definition_ref', value: $capture['definition_ref'] ?? 'task-completion/1');
        $versionRef = self::nonEmpty(violations: $violations, path: 'completion_capture.version_ref', value: $capture['version_ref'] ?? 'task-completion/1');

        $violations->failIfAny();

        return new self(wakeupEnabled: $wakeupEnabled, wakeupHandlerKind: $handlerKind, wakeupHandlerVersion: $handlerVersion, wakeupHandler: $handler, wakeupAcceptLegacyUnstamped: $acceptLegacy, completionCaptureEnabled: $captureEnabled, completionCaptureDefinitionRef: $definitionRef, completionCaptureVersionRef: $versionRef);
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
