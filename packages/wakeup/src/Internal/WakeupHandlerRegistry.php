<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Internal;

use Closure;
use CoreX\Modules\Data\ExtensionStamp;
use CoreX\Wakeup\Contracts\DueWakeupHandler;
use CoreX\Wakeup\Data\WakeupHandlerDefinition;
use InvalidArgumentException;
use RuntimeException;

final readonly class WakeupHandlerRegistry
{
    public const Schema = 'wakeup-payload/2';

    public const Point = 'wakeup.handler';

    public const ModuleFree = 'module-free';

    /**
     * @param  array<string, WakeupHandlerDefinition>  $definitions
     * @param  array<string, DueWakeupHandler>  $handlers
     */
    private function __construct(
        private array $definitions,
        private array $handlers,
        private string $registryFingerprint,
    ) {}

    /**
     * @param  iterable<WakeupHandlerDefinition>  $definitions
     * @param  Closure(string): mixed  $resolve
     */
    public static function compile(
        iterable $definitions,
        Closure $resolve,
        string $registryFingerprint,
    ): self {
        if ($registryFingerprint === '' || trim($registryFingerprint) !== $registryFingerprint) {
            throw new InvalidArgumentException('Wakeup registry fingerprint must be a non-empty stable reference.');
        }

        $compiledDefinitions = [];
        $handlers = [];

        foreach ($definitions as $definition) {
            if (isset($compiledDefinitions[$definition->kind])) {
                throw new RuntimeException("Duplicate wakeup handler definition [{$definition->kind}].");
            }

            $handler = $resolve($definition->handler);

            if (! $handler instanceof DueWakeupHandler) {
                throw new RuntimeException("Wakeup handler [{$definition->handler}] must resolve to DueWakeupHandler.");
            }

            $compiledDefinitions[$definition->kind] = $definition;
            $handlers[$definition->kind] = $handler;
        }

        return new self(
            definitions: $compiledDefinitions,
            handlers: $handlers,
            registryFingerprint: $registryFingerprint,
        );
    }

    public function definition(string $kind): ?WakeupHandlerDefinition
    {
        return $this->definitions[$kind] ?? null;
    }

    public function handler(string $kind): ?DueWakeupHandler
    {
        return $this->handlers[$kind] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{_corex: array{schema: string, extension: array{point: string, id: string, version: string, registryFingerprint: string}}, data: array<string, mixed>}
     */
    public function envelope(WakeupHandlerDefinition $definition, array $data): array
    {
        return [
            '_corex' => [
                'schema' => self::Schema,
                'extension' => $this->stamp($definition)->toArray(),
            ],
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public function replayIdentity(array $envelope): array
    {
        $identity = $envelope;

        if (is_array($identity['_corex']['extension'] ?? null)) {
            unset($identity['_corex']['extension']['registryFingerprint']);
        }

        return $identity;
    }

    /**
     * @return array{status: 'ready'|'legacy-admitted'|'handler-unavailable'|'handler-stamp-missing'|'handler-version-mismatch', data?: array<string, mixed>}
     */
    public function inspectPayload(string $kind, mixed $payload): array
    {
        $definition = $this->definition($kind);

        if ($definition === null) {
            return ['status' => 'handler-unavailable'];
        }

        if (! is_array($payload)) {
            return ['status' => 'handler-stamp-missing'];
        }

        $metadata = $payload['_corex'] ?? null;

        if (! is_array($metadata) || ! array_key_exists('schema', $metadata)) {
            if (! $definition->acceptLegacyUnstamped) {
                return ['status' => 'handler-stamp-missing'];
            }

            return ['status' => 'legacy-admitted', 'data' => $payload];
        }

        $data = $payload['data'] ?? null;

        if (($metadata['schema'] ?? null) !== self::Schema
            || ! is_array($metadata['extension'] ?? null)
            || ! is_array($data)) {
            return ['status' => 'handler-stamp-missing'];
        }

        try {
            $rawStamp = $metadata['extension'];
            foreach (['point', 'id', 'version', 'registryFingerprint'] as $field) {
                if (! is_string($rawStamp[$field] ?? null)) {
                    throw new InvalidArgumentException('Invalid extension stamp.');
                }
            }
            /** @var array{point: string, id: string, version: string, registryFingerprint: string} $rawStamp */
            $stamp = ExtensionStamp::fromArray($rawStamp);
        } catch (InvalidArgumentException) {
            return ['status' => 'handler-stamp-missing'];
        }

        if ($stamp->point !== self::Point
            || $stamp->id !== $definition->kind
            || $stamp->version !== $definition->version) {
            return ['status' => 'handler-version-mismatch'];
        }

        return ['status' => 'ready', 'data' => $data];
    }

    private function stamp(WakeupHandlerDefinition $definition): ExtensionStamp
    {
        return new ExtensionStamp(
            point: self::Point,
            id: $definition->kind,
            version: $definition->version,
            registryFingerprint: $this->registryFingerprint,
        );
    }
}
