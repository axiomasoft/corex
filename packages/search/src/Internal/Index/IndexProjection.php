<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexDocument;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordRefs;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class IndexProjection
{
    public function __construct(public SearchSource $source, private SearchProjectionContributor $projection, private FieldUsePolicy $policy, private DataScopeAuthorizer $auth) {}

    /** @return array{string,string} */
    public function witnesses(DataScope $scope, EntityHandle $entity): array
    {
        $schema = $this->projection->describe(new ProjectionContext($scope, $entity));
        $grants = [];
        foreach ($schema->fields as $field) {
            $plan = $this->policy->plan($scope, $entity, new FieldUseRequest(new FieldSelection([$field->id]), FieldUse::Read));

            if ($plan->kind !== FieldUsePlanKind::Unconditional) {
                throw new LogicException('Indexing requires a service grant across the indexed corpus.');
            }
            $grants[$field->id] = $plan->witness;

            if ($field->authorizationField !== null) {
                $underlying = $this->policy->plan($scope, $entity, new FieldUseRequest(new FieldSelection([$field->authorizationField]), FieldUse::Read));

                if ($underlying->kind !== FieldUsePlanKind::Unconditional) {
                    throw new LogicException('Indexing requires a service grant for the underlying field.');
                }
                $grants[$field->id] = [$plan->witness, $underlying->witness];
            }
        }
        ksort($grants, SORT_STRING);

        return [$schema->revision, hash('sha256', json_encode($grants, JSON_THROW_ON_ERROR))];
    }

    /** A saved plan may outlive the record or the service grant that produced it. */
    public function current(IndexBatch $batch): bool
    {
        if ($batch->documents === []) {
            return true;
        }
        $target = $batch->target;
        [$mapping] = $this->witnesses($target->scope, $target->entity);

        if ($mapping !== $target->mappingDigest) {
            throw new LogicException('Search index mapping changed before submission.');
        }
        foreach ($batch->documents as $planned) {
            $current = $this->document($target->scope, $target->entity, $planned->record);

            if ($current === null ? $batch->action !== IndexAction::DeleteRecords
                : ($batch->action !== IndexAction::Upsert || $planned->values->values !== $current->values->values)) {
                return false;
            }
        }

        return true;
    }

    public function document(DataScope $scope, EntityHandle $entity, RecordRef $record): ?IndexDocument
    {
        $loaded = $this->source->load($scope, new RecordRefs([$record]))->records;

        if ($loaded === []) {
            return null;
        }

        if (count($loaded) !== 1 || $loaded[0]->record->id !== $record->id || $loaded[0]->record->entity !== $record->entity) {
            throw new LogicException('Index source returned an unexpected identity.');
        }

        if (! $this->auth->authorize($scope, $record, new FieldSelection([]), FieldUse::Read)->allowed) {
            throw new LogicException('Index service cannot read the record.');
        }
        $context = new ProjectionContext($scope, $entity);
        $fields = [];
        $schema = $this->projection->describe($context);
        foreach ($schema->fields as $field) {
            if (! $this->auth->authorize($scope, $record, new FieldSelection([$field->id]), FieldUse::Read)->allowed
                || ($field->authorizationField !== null
                    && ! $this->auth->authorize($scope, $record, new FieldSelection([$field->authorizationField]), FieldUse::Read)->allowed)) {
                throw new LogicException('Index service cannot read the declared field.');
            }
            $fields[] = $field->id;
        }
        $extra = $this->projection->project($context, $record, new ProjectionSelection($fields));
        $values = array_intersect_key($loaded[0]->values->values, array_flip($fields));
        foreach ($extra->values as $id => $value) {
            if (! in_array($id, $fields, true) || (array_key_exists($id, $values) && $values[$id] !== $value)) {
                throw new LogicException('Index contributor returned an inconsistent field projection.');
            }
            $values[$id] = $value;
        }
        $physicalValues = [];
        foreach ($schema->fields as $field) {
            if (! $field->type->accepts($values[$field->id] ?? null)) {
                throw new LogicException('Index projection differs from the declared field type.');
            }

            if (array_key_exists($field->id, $values)) {
                $physicalValues[$field->physicalId ?? $field->id] = $values[$field->id];
            }
        }
        ksort($physicalValues, SORT_STRING);

        return new IndexDocument($record, new ProjectedValues($physicalValues));
    }
}
