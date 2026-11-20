<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider and runtime checks. */
#[Internal]
final readonly class SchedulerOptions
{
    /** @param  list<WakeupHandlerDefinition>  $handlers */
    public function __construct(
        public int $leaseSeconds,
        public int $maxAttempts,
        public array $handlers,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $leaseSeconds = $config['lease_seconds'] ?? 60;

        if (! is_int($leaseSeconds) || $leaseSeconds < 1) {
            $violations->add(path: 'lease_seconds', message: 'must be a positive integer');
            $leaseSeconds = 60;
        }

        $maxAttempts = $config['max_attempts'] ?? 5;

        if (! is_int($maxAttempts) || $maxAttempts < 1) {
            $violations->add(path: 'max_attempts', message: 'must be a positive integer');
            $maxAttempts = 5;
        }

        $configured = $config['handlers'] ?? [];

        if (! is_array($configured)) {
            $violations->add(path: 'handlers', message: 'must be a map of handler definitions keyed by kind');
            $configured = [];
        }

        $handlers = [];
        foreach ($configured as $kind => $definition) {
            $path = 'handlers.'.$kind;

            if (! is_string($kind)
                || ! is_array($definition)
                || ! is_string($definition['handler'] ?? null)
                || ! is_string($definition['version'] ?? null)
                || array_diff(array_keys($definition), ['handler', 'version']) !== []) {
                $violations->add(path: $path, message: 'requires handler, version as its only fields');

                continue;
            }

            try {
                $handlers[] = new WakeupHandlerDefinition(
                    kind: $kind,
                    version: $definition['version'],
                    handler: $definition['handler'],
                );
            } catch (InvalidArgumentException $exception) {
                $violations->add(path: $path, message: $exception->getMessage());
            }
        }

        $violations->failIfAny();

        return new self(leaseSeconds: $leaseSeconds, maxAttempts: $maxAttempts, handlers: $handlers);
    }
}
