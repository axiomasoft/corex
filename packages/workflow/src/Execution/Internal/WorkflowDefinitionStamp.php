<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use CoreX\Workflow\Data\TriggerRequest;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Packman\Modules\Data\Compiler\ExtensionStamp;

/**
 * Versioned workflow definition identity carried inside an existing intent payload.
 *
 * `registryFingerprint` is stored provenance and is not part of the replay hash.
 */
#[Internal]
final class WorkflowDefinitionStamp
{
    public const string SCHEMA = 'workflow-definition/2';

    public const string POINT = 'workflow.trigger';

    public const string MODULE_FREE = 'module-free';

    public static function assertStableRef(string $field, string $value): void
    {
        if ($value === '' || trim($value) !== $value) {
            throw new InvalidArgumentException("Workflow {$field} must be a non-empty stable reference.");
        }
    }

    public static function fromRequest(TriggerRequest $request, ?string $registryFingerprint): ExtensionStamp
    {
        self::assertStableRef(field: 'definitionRef', value: $request->definitionRef);
        self::assertStableRef(field: 'versionRef', value: $request->versionRef);
        self::assertStableRef(field: 'module', value: $request->module);
        self::assertStableRef(field: 'entityHandle', value: $request->entityHandle);
        self::assertStableRef(field: 'eventName', value: $request->eventName);

        return new ExtensionStamp(
            point: self::POINT,
            id: $request->module.':'.$request->entityHandle.':'.$request->eventName,
            version: $request->versionRef,
            registryFingerprint: $registryFingerprint ?? self::MODULE_FREE,
        );
    }

    /**
     * @param  array<string, mixed>  $businessPayload
     * @return array<string, mixed>
     */
    public static function storedPayload(array $businessPayload, ExtensionStamp $stamp): array
    {
        $stored = $businessPayload;
        $stored['identity'] = [
            'schema' => self::SCHEMA,
            'point' => $stamp->point,
            'id' => $stamp->id,
            'version' => $stamp->version,
        ];
        $stored['registryFingerprint'] = $stamp->registryFingerprint;

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function canonicalPayload(array $stored): array
    {
        unset($stored['registryFingerprint']);

        return $stored;
    }

    /** @param array<string, mixed> $canonical */
    public static function payloadHash(string $kind, string $requestKey, array $canonical): string
    {
        $encoded = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', self::SCHEMA."\0{$kind}\0{$requestKey}\0{$encoded}");
    }

    /** @param array<string, mixed> $stored */
    public static function matches(array $stored, ExtensionStamp $stamp): bool
    {
        $identity = $stored['identity'] ?? null;

        return is_array($identity)
            && ($identity['schema'] ?? null) === self::SCHEMA
            && ($identity['point'] ?? null) === $stamp->point
            && ($identity['id'] ?? null) === $stamp->id
            && ($identity['version'] ?? null) === $stamp->version;
    }
}
