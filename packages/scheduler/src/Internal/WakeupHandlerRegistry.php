<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use Closure;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Packman\Modules\Data\Compiler\ExtensionStamp;
use RuntimeException;

#[Internal]
final readonly class WakeupHandlerRegistry
{
    public const string Schema = 'wakeup-payload/2';

    public const string Point = 'wakeup.handler';

    public const string ModuleFree = 'module-free';

    /**
     * @param  array<string, WakeupHandlerDefinition>  $definitions
     */
    private function __construct(
        private array $definitions,
        private string $registryFingerprint,
    ) {}

    /**
     * @param  iterable<WakeupHandlerDefinition>  $definitions
     */
    public static function compile(
        iterable $definitions,
        string $registryFingerprint,
    ): self {
        if ($registryFingerprint === '' || trim($registryFingerprint) !== $registryFingerprint) {
            throw new InvalidArgumentException('Wakeup registry fingerprint must be a non-empty stable reference.');
        }

        $compiledDefinitions = [];

        foreach ($definitions as $definition) {
            if (isset($compiledDefinitions[$definition->kind])) {
                throw new RuntimeException("Duplicate wakeup handler definition [{$definition->kind}].");
            }

            $compiledDefinitions[$definition->kind] = $definition;
        }

        return new self(
            definitions: $compiledDefinitions,
            registryFingerprint: $registryFingerprint,
        );
    }

    public function definition(string $kind): ?WakeupHandlerDefinition
    {
        return $this->definitions[$kind] ?? null;
    }

    /** @param Closure(class-string<DueWakeupHandler>): mixed $resolve */
    public function handler(string $kind, Closure $resolve): ?DueWakeupHandler
    {
        $definition = $this->definition($kind);

        if ($definition === null) {
            return null;
        }
        $handler = $resolve($definition->handler);

        if (! $handler instanceof DueWakeupHandler) {
            throw new RuntimeException("Wakeup handler [{$definition->handler}] must resolve to DueWakeupHandler.");
        }

        return $handler;
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
            'data' => Value::map($data),
        ];
    }

    /**
     * @return array{status: 'ready'|'handler-unavailable'|'handler-stamp-missing'|'handler-version-mismatch', data?: array<string, mixed>}
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

        $data = $payload['data'] ?? null;

        if (! is_array($metadata) || ($metadata['schema'] ?? null) !== self::Schema
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

        return ['status' => 'ready', 'data' => Value::map($data)];
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
