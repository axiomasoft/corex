<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Values;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Internal\InvalidAttributeValue;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;

final readonly class AttributeWriteGuard
{
    private const string DENIED_MESSAGE = 'The attribute write is not allowed.';

    public function __construct(
        private FieldCatalogue $catalogue,
        private DataScopeAuthorizer $authorizer,
        private FieldValueValidator $validator,
    ) {}

    public function assertCanWrite(DataScope $scope, RecordRef $record, ValuePatch $patch): FieldSnapshot
    {
        $snapshot = $this->catalogue->snapshot(scope: $scope, entity: $record->entity);
        $definitions = $this->definitions($snapshot);

        foreach (array_keys($patch->values) as $code) {
            if (! isset($definitions[$code])) {
                $this->deny('attributes.field.missing');
            }
        }

        $this->assertSchemaCompatibility(snapshot: $snapshot, definitions: $definitions, patch: $patch);

        foreach (['attributes.subject', 'attributes.view', 'attributes.write', 'attributes.mutate'] as $action) {
            foreach (array_keys($patch->values) as $code) {
                $definition = $definitions[$code];
                $attributeId = is_string($definition['id'] ?? null)
                    ? $definition['id']
                    : 'legacy:'.$code;
                $decision = $this->authorizer->authorize(
                    scope: $scope,
                    entity: $record->entity,
                    subjectId: $this->resource($scope, $record, $attributeId),
                    fields: [$code],
                    action: $action,
                );

                if (! $decision->allowed) {
                    $this->deny($action.'.denied');
                }
            }
        }

        try {
            $this->validator->validateTypes(snapshot: $snapshot, patch: $patch);
        } catch (InvalidAttributeValue) {
            $this->deny('attributes.value.type');
        }

        try {
            $this->validator->validateConstraints(snapshot: $snapshot, patch: $patch);
        } catch (InvalidAttributeValue) {
            $this->deny('attributes.value.constraint');
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     */
    private function assertSchemaCompatibility(
        FieldSnapshot $snapshot,
        array $definitions,
        ValuePatch $patch,
    ): void {
        if ($patch->expectedSchemaHash !== null
            && ! hash_equals($snapshot->schemaHash, $patch->expectedSchemaHash)) {
            $this->deny('attributes.schema.stale');
        }

        foreach (array_keys($patch->values) as $code) {
            $definition = $definitions[$code];
            $lifecycle = $definition['lifecycle'] ?? 'active';

            if (! is_string($lifecycle) || in_array($lifecycle, ['draft', 'retired'], true)) {
                $this->deny('attributes.schema.not_writable');
            }

            if ($patch->expectedSchemaHash === null
                && ($lifecycle !== 'active'
                    || count($this->keys($definition, 'read_keys', $code)) !== 1
                    || count($this->keys($definition, 'write_keys', $code)) !== 1)) {
                $this->deny('attributes.schema.precondition_required');
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(FieldSnapshot $snapshot): array
    {
        $definitions = [];
        foreach ($snapshot->activeFields as $definition) {
            $code = is_array($definition) ? ($definition['code'] ?? $definition['key'] ?? null) : null;

            if (! is_array($definition) || ! is_string($code) || $code === '') {
                $this->deny('attributes.catalogue.malformed');
            }

            $definitions[$code] = $definition;
        }

        return $definitions;
    }

    /** @param array<string, mixed> $definition
     * @return list<string>
     */
    private function keys(array $definition, string $name, string $fallback): array
    {
        $keys = $definition[$name] ?? [];

        if ($keys === []) {
            return [$fallback];
        }

        if (! is_array($keys) || ! array_is_list($keys)) {
            $this->deny('attributes.catalogue.malformed');
        }

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '') {
                $this->deny('attributes.catalogue.malformed');
            }
        }

        return $keys;
    }

    private function resource(DataScope $scope, RecordRef $record, string $attributeId): string
    {
        $payload = json_encode([
            'account' => $scope->accountId,
            'workspace' => $scope->workspaceId,
            'boxed' => $scope->boxed,
            'connection' => $scope->connectionIdentity,
            'entity' => $record->entity,
            'record' => $record->id,
            'attribute' => $attributeId,
        ], JSON_THROW_ON_ERROR);

        return 'attributes:'.hash('sha256', $payload);
    }

    private function deny(string $reasonCode): never
    {
        $reason = new ScopeDenied(self::DENIED_MESSAGE, reasonCode: $reasonCode);

        throw new InvalidAttributeValue(self::DENIED_MESSAGE, previous: $reason);
    }
}
