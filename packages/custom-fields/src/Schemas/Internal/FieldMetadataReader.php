<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldMetadata;
use CoreX\CustomFields\Schemas\Data\FieldDescription;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Contracts\FieldUseSnapshot;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use CoreX\Data\Laravel\JsonDocuments;
use Packman\Attributes\Internal;

#[Internal]
final readonly class FieldMetadataReader implements FieldMetadata
{
    public function __construct(private WorkScopeRunner $runner, private FieldCatalogue $catalogue, private FieldUsePolicy $policy) {}

    public function describe(DataScope $scope, EntityHandle $entity): FieldDescription
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $entity): FieldDescription {
            $policy = $this->policy;

            if (! $policy instanceof FieldUseSnapshot) {
                throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            }

            $this->entityRead(scope: $scope, entity: $entity);
            // Bootstrapping the schema head can write. Finish it before taking the release snapshot.
            $this->catalogue->snapshot(scope: $scope, entity: $entity);

            return $policy->inSnapshot(scope: $scope, entity: $entity, read: fn (): FieldDescription => $this->view(scope: $scope, entity: $entity))
                ?? throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        });
    }

    private function entityRead(DataScope $scope, EntityHandle $entity): FieldUsePlan
    {
        $read = $this->policy->plan(scope: $scope, entity: $entity, request: new FieldUseRequest(fields: new FieldSelection([]), use: FieldUse::Read));

        if ($read->kind === FieldUsePlanKind::Denied || ($read->kind === FieldUsePlanKind::ScopedPredicate && $read->recordIds === [])) {
            throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        }

        return $read;
    }

    private function view(DataScope $scope, EntityHandle $entity): FieldDescription
    {
        $read = $this->entityRead(scope: $scope, entity: $entity);
        $snapshot = $this->catalogue->snapshot(scope: $scope, entity: $entity);
        $fields = [];
        $witnesses = [$read, $snapshot->revision->value, $snapshot->mappingDigest];
        foreach ($snapshot->fields as $id => $field) {
            $uses = [];
            foreach ($field->uses as $use) {
                $grant = $this->policy->plan(scope: $scope, entity: $entity, request: new FieldUseRequest(fields: new FieldSelection([$id]), use: $use));
                $witnesses[] = [$id, $use->value, $grant];

                // Metadata advertises uses which hold for the whole exposed view.
                if ($grant->kind === FieldUsePlanKind::Unconditional) {
                    $uses[] = $use->value;
                }
            }

            if ($uses !== []) {
                $fields[] = ['id' => $id, 'type' => $field->type->value, 'uses' => $uses,
                    'operators' => in_array(FieldUse::Filter->value, $uses, true)
                        ? array_column($snapshot->operators[$id]->operators ?? [], 'value') : []];
            }
        }

        return new FieldDescription(body: JsonDocuments::capture([
            'version' => 'v2', 'entity' => $entity->value, 'schema_revision' => $snapshot->revision->value,
            'capabilities' => ['describe' => true], 'fields' => $fields,
        ]), witness: hash('sha256', json_encode($witnesses, JSON_THROW_ON_ERROR)));
    }
}
