<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\WorkScopeRunner;
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
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchMetadataDenied;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\LiveCandidateDriver;
use CoreX\Search\Query\Contracts\SearchMetadata;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\Search\Query\Data\SearchDescription;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SearchMetadataReader implements SearchMetadata
{
    /**
     * @param  Closure(): CandidateDriver  $driver
     * @param  Closure(): IndexDriver  $index
     */
    public function __construct(
        private WorkScopeRunner $runner,
        private CurrentWorkScope $current,
        private FieldUsePolicy $policy,
        private string $backend,
        private string $cluster,
        private Closure $driver,
        private SearchProjectionContributor $projection,
        private ActiveGenerations $generations,
        private Closure $index,
    ) {}

    public function describe(DataScope $scope, EntityHandle $entity): SearchDescription
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $entity): SearchDescription {
            $this->current->assert($scope);
            $policy = $this->policy;

            if (! $policy instanceof FieldUseSnapshot) {
                throw new SearchMetadataDenied('Search metadata is not authorized.');
            }

            $context = new ProjectionContext(scope: $scope, entity: $entity);
            $read = $this->entityRead($context);

            if ($this->backend === 'null') {
                throw IndexNotReady::unpublished();
            }

            $schema = $this->projection->describe($context);
            $driver = ($this->driver)();
            $target = $this->target(context: $context, driver: $driver, mapping: $schema->revision);
            $this->assertCorpus(read: $read, caps: $driver->capabilities());

            if (! $driver instanceof LiveCandidateDriver && ! ($this->index)()->canary($target)) {
                throw IndexNotReady::unpublished();
            }

            $generation = SearchNamespace::generation($target);

            // The canary is remote and may have overlapped a revocation or publication.
            $view = $policy->inSnapshot(scope: $scope, entity: $entity, read: function () use ($context, $driver, $target, $generation): SearchDescription {
                $this->current->assert($context->scope);
                $read = $this->entityRead($context);
                $schema = $this->projection->describe($context);
                $current = $this->target(context: $context, driver: $driver, mapping: $schema->revision);

                if ($target->mappingDigest !== $schema->revision || SearchNamespace::generation($current) !== $generation) {
                    throw IndexNotReady::unpublished();
                }

                return $this->view(context: $context, read: $read, schema: $schema, generation: $generation, caps: $driver->capabilities());
            });
            $this->current->assert($scope);

            return $view ?? throw new SearchMetadataDenied('Search metadata is not authorized.');
        });
    }

    private function entityRead(ProjectionContext $context): FieldUsePlan
    {
        $read = $this->plan(context: $context, use: FieldUse::Read);

        if ($read->kind === FieldUsePlanKind::Denied || ($read->kind === FieldUsePlanKind::ScopedPredicate && $read->recordIds === [])) {
            throw new SearchMetadataDenied('Search metadata is not authorized.');
        }

        return $read;
    }

    private function assertCorpus(FieldUsePlan $read, DriverCapabilities $caps): void
    {
        if ($read->kind === FieldUsePlanKind::ScopedPredicate && ! $caps->scopedCorpus) {
            throw SearchFeatureUnavailable::unsupported();
        }
    }

    private function target(ProjectionContext $context, CandidateDriver $driver, string $mapping): IndexTarget
    {
        $target = $driver instanceof LiveCandidateDriver
            ? $driver->target(scope: $context->scope, entity: $context->entity, mappingDigest: $mapping)
            : $this->generations->active(scope: $context->scope, entity: $context->entity);

        if ($target === null || SearchNamespace::scope($target->scope) !== SearchNamespace::scope($context->scope)
            || $target->backend !== $this->backend || $target->cluster !== $this->cluster
            || $target->entity->value !== $context->entity->value || $target->mappingDigest !== $mapping) {
            throw IndexNotReady::unpublished();
        }

        return $target;
    }

    private function view(ProjectionContext $context, FieldUsePlan $read, ProjectionFields $schema, string $generation, DriverCapabilities $caps): SearchDescription
    {
        $this->assertCorpus(read: $read, caps: $caps);
        $fields = [];
        $enabled = ['read' => false, 'search' => false, 'filter' => false, 'sort' => false];
        $witnesses = [$read, $schema->revision, $generation, $caps];
        foreach ($schema->fields as $field) {
            $uses = [];
            foreach ($field->uses as $use) {
                $supported = match ($use) {
                    FieldUse::Read => true,
                    FieldUse::Search => $caps->text && $field->type->value === 'text',
                    FieldUse::Filter => $caps->filter,
                    FieldUse::Sort => $caps->sort,
                    default => false,
                };

                if (! $supported) {
                    continue;
                }
                $ids = array_unique([$field->id, $field->authorizationField ?? $field->id]);
                $allowed = true;
                foreach ($ids as $id) {
                    $grant = $this->plan(context: $context, use: $use, field: $id);
                    $witnesses[] = [$id, $use->value, $grant];
                    $allowed = $allowed && $grant->kind === FieldUsePlanKind::Unconditional;

                    if ($field->predicateRequiresRead && $use !== FieldUse::Read) {
                        $prerequisite = $this->plan(context: $context, use: FieldUse::Read, field: $id);
                        $witnesses[] = [$id, 'predicate-read', $prerequisite];
                        $allowed = $allowed && $prerequisite->kind === FieldUsePlanKind::Unconditional;
                    }
                }

                if ($allowed) {
                    $uses[] = $use->value;
                    $enabled[$use->value] = true;
                }
            }

            if ($uses !== []) {
                $fields[] = ['id' => $field->id, 'type' => $field->type->value, 'uses' => $uses];
            }
        }

        return new SearchDescription(body: JsonDocuments::capture([
            'version' => 'v2', 'entity' => $context->entity->value, 'schema_revision' => $schema->revision,
            'capabilities' => $enabled, 'fields' => $fields,
        ]), witness: hash('sha256', json_encode($witnesses, JSON_THROW_ON_ERROR)));
    }

    private function plan(ProjectionContext $context, FieldUse $use, ?string $field = null): FieldUsePlan
    {
        return $this->policy->plan(scope: $context->scope, entity: $context->entity,
            request: new FieldUseRequest(fields: new FieldSelection($field === null ? [] : [$field]), use: $use));
    }
}
