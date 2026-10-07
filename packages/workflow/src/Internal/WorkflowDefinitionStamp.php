<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use CoreX\Modules\Data\ExtensionStamp;
use CoreX\Workflow\Data\TriggerRequest;
use InvalidArgumentException;

/**
 * Versioned workflow definition identity carried inside an existing intent payload.
 *
 * `registryFingerprint` is stored provenance and is not part of the replay hash.
 */
final class WorkflowDefinitionStamp
{
    public const string SCHEMA = 'workflow-definition/1';

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
        $encoded = json_encode($canonical, JSON_THROW_ON_ERROR);

        return hash('sha256', self::SCHEMA."\0{$kind}\0{$requestKey}\0{$encoded}");
    }

    /** @param array<string, mixed> $businessPayload */
    public static function legacyPayloadHash(string $kind, string $requestKey, array $businessPayload): string
    {
        $encoded = json_encode($businessPayload, JSON_THROW_ON_ERROR);

        return hash('sha256', "engine-binding/1\0{$kind}\0{$requestKey}\0{$encoded}");
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return 'versioned'|'legacy'|'missing'
     */
    public static function schemaOf(?array $stored, string $definitionRef, string $versionRef): string
    {
        $identity = $stored['identity'] ?? null;
        $schema = is_array($identity) ? ($identity['schema'] ?? null) : null;

        if ($schema === self::SCHEMA) {
            return 'versioned';
        }

        if (trim($definitionRef) !== '' && trim($versionRef) !== '') {
            return 'legacy';
        }

        return 'missing';
    }
}
