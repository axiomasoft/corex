<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Values;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\InvalidAttributeValue;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;

#[Internal]
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

        if (! $this->authorizer->authorize($scope, $record, new FieldSelection([]), FieldUse::Read)->allowed) {
            $this->deny('attributes.subject.denied');
        }

        foreach (array_keys($patch->values) as $code) {
            $definition = $definitions[$code];
            $attributeId = is_string($definition['id'] ?? null)
                ? $definition['id']
                : AttributeId::legacy($record->entity, $code)->value;
            $selection = new FieldSelection([$attributeId]);

            if (! $this->authorizer->authorize($scope, $record, $selection, FieldUse::Read)->allowed) {
                $this->deny('attributes.view.denied');
            }

            if (! $this->authorizer->authorize($scope, $record, $selection, FieldUse::Write)->allowed) {
                $this->deny('attributes.write.denied');
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
                    || count($this->keys(definition: $definition, name: 'read_keys', fallback: $code)) !== 1
                    || count($this->keys(definition: $definition, name: 'write_keys', fallback: $code)) !== 1)) {
                $this->deny('attributes.schema.precondition_required');
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(FieldSnapshot $snapshot): array
    {
        $definitions = [];
        foreach ($snapshot->activeFields as $definition) {
            $code = ($definition['code'] ?? $definition['key'] ?? null);

            if (! is_string($code) || $code === '') {
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

    private function deny(string $reasonCode): never
    {
        $reason = new ScopeDenied(message: self::DENIED_MESSAGE, reasonCode: $reasonCode);

        throw new InvalidAttributeValue(self::DENIED_MESSAGE, previous: $reason);
    }
}
