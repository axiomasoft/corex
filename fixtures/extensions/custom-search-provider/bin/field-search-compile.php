<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemaDependencies;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Schemas\Data\SchemaPlan;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Panels\Contracts\SavedViewInventory;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\RebuildInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Packman\Data\Laravel\JsonDocuments;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Panels\Enums\PanelErrorCode;
use Packman\Panels\Exceptions\SavedViews\SavedViewException;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Packman\Panels\SavedViews\Contracts\ViewSchemaProvider;
use Packman\Panels\SavedViews\Data\SavedQuery;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewAction;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewField;
use Packman\Panels\SavedViews\Data\ViewSchema;
use Packman\Panels\SavedViews\Data\ViewScope;
use Packman\Panels\SavedViews\Data\ViewSubject;
use ThirdParty\Search\Module;

final readonly class FixtureSavedViewSchema implements ViewSchemaProvider
{
    public function __construct(private FieldCatalogue $catalogue, private DataScope $scope, private ViewContext $actor) {}

    public function schema(ViewContext $context, string $entityHandle): ViewSchema
    {
        verifyFixture($context === $this->actor, 'The saved-view schema must use the same trusted actor.');
        $snapshot = $this->catalogue->snapshot($this->scope, new EntityHandle($entityHandle));
        $fields = [];
        $grammar = [];

        foreach ($snapshot->fields as $id => $field) {
            if (! in_array(FieldUse::Read, $field->uses, true)) {
                continue;
            }
            $operatorSet = $snapshot->operators[$id] ?? null;
            $operators = $operatorSet === null ? [] : array_column($operatorSet->operators, 'value');
            sort($operators, SORT_STRING);
            $viewField = new ViewField(
                key: $id, type: $field->type->value, operators: $operators, visible: true,
                filterable: $operators !== [], sortable: in_array(FieldUse::Sort, $field->uses, true),
                projectable: true, mappingKey: $id,
            );
            $fields[$id] = $viewField;
            $grammar[$id] = [$viewField, $field->codecVersion, $field->nullable, $field->constraints, $field->localizationPolicy];
        }
        ksort($grammar, SORT_STRING);

        return new ViewSchema(hash('sha256', json_encode($grammar, JSON_THROW_ON_ERROR)), $entityHandle, array_values($fields));
    }

    /** @return array{SavedQuery, FieldSchemaSnapshot} */
    public function compile(SavedViewRepository $views, ViewScope $viewScope, string $id): array
    {
        $entity = new EntityHandle($viewScope->entityHandle);
        $before = $this->catalogue->snapshot($this->scope, $entity);
        $query = $views->compile($this->actor, $id);
        $after = $this->catalogue->snapshot($this->scope, $entity);
        verifyFixture($before == $after, 'The effective authorized Field catalogue changed during saved-view compilation.');

        return [$query, $after];
    }
}

final readonly class FixtureSavedViewReferences implements FieldSchemaDependencies
{
    public function __construct(
        private FixtureSavedViewSchema $schema,
        private SavedViewRepository $views,
        private DataScope $scope,
        private ViewContext $actor,
        private ViewScope $viewScope,
    ) {}

    public function references(DataScope $scope, EntityHandle $entity, FieldId $field, SchemaRevision $revision): array
    {
        if ($scope != $this->scope || $entity->value !== $this->viewScope->entityHandle) {
            return [];
        }
        $references = [];
        foreach ($this->views->viewsFor($this->actor, $this->viewScope) as $view) {
            [$query, $current] = $this->schema->compile($this->views, $this->viewScope, $view->id);
            verifyFixture($current->revision->value === $revision->value, 'The saved dependency must use the requested effective Field revision.');

            if (in_array($field->value, $query->projection, true) || in_array($field->value, array_column($query->sort, 'field'), true)
                || $query->groupBy === $field->value || $this->filterUses(JsonDocuments::array($query->filters), $field->value)) {
                $references[] = 'saved-view:'.$view->id;
            }
        }

        return $references;
    }

    private function filterUses(array $node, string $field): bool
    {
        return ($node['field'] ?? null) === $field
            || array_any($node['args'] ?? [], fn (array $child): bool => $this->filterUses($child, $field));
    }
}

final readonly class FixtureSavedViewInventory implements SavedViewInventory
{
    public function __construct(private Application $app, private DataScope $scope, private ViewContext $actor, private ViewScope $view) {}

    public function connection(DataScope $scope): ConnectionInterface
    {
        verifyFixture($scope == $this->scope, 'Unknown fixture saved-view account.');
        $connection = $this->app->make(DatabaseManager::class)->connection();
        $this->app->make(CurrentWorkScope::class)->assert($scope, $connection);

        return $connection;
    }

    public function scopes(DataScope $scope): array
    {
        $this->connection($scope);

        return [[$this->actor, $this->view]];
    }

    public function targets(DataScope $scope, ViewContext $context, string $operation, ?string $id, ?SaveViewInput $input): array
    {
        $this->connection($scope);
        verifyFixture($context === $this->actor && $operation === 'save' && $id === null && $input?->scope === $this->view,
            'Unknown fixture saved-view write.');

        return [new ModuleRef(Module::Name), new ModuleRef('axiomasoft/corex-custom-fields')];
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        throw new LifecycleBlocked('The field-search fixture does not supply destructive lifecycle exclusion.');
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        throw new LifecycleBlocked('The field-search fixture has no lifecycle lease.');
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        throw new LifecycleBlocked('The field-search fixture has no lifecycle lease.');
    }
}

function compileFieldSearch(Application $app, DataScope $scope, TenantContext $tenant, EntityHandle $entity, array $fixture): array
{
    verifyFixture($app['config']->get('corex-search-custom-fields.enabled') === true, 'The installed Field Search bridge must be enabled.');
    $app->make(Gate::class)->define('corex.lifecycle.enable', static fn (ScopePrincipal $actor, DataScope $checked, ModuleRef $target): bool => $checked == $scope
        && $actor->kind === $scope->principalKind && $actor->id === $scope->principalId && $target->package === Module::Name);
    $app->make(ModuleLifecycle::class)->enable(Module::Name);
    $driver = $app->make(IndexDriver::class);
    verifyFixture(get_class($driver) === $fixture['class'] && in_array($driver, iterator_to_array($app->tagged(CandidateDriver::class)), true),
        'Optional integration changed the external Search driver.');

    $field = new FieldId($fixture['field']);
    $catalogue = $app->make(FieldCatalogue::class);
    $before = $catalogue->snapshot($scope, $entity);
    publishFieldSchema($app, $scope, $before, FieldSchemaChange::add(new FieldDefinition(
        $field, $entity, 'asset_code', new FieldTypeId('text'), 'runtime',
        uses: [FieldUse::Read, FieldUse::Filter, FieldUse::Sort], presentation: ['label' => 'Asset code'],
    )));
    $projection = $app->make(SearchProjectionContributor::class);
    $context = new ProjectionContext($scope, $entity);
    $described = $projection->describe($context);
    $rebuild = $app->make(SearchRebuilds::class)->plan($scope, new RebuildInput($entity));
    verifyFixture(array_column($described->fields, 'id') === [$field->value, 'title'] && $rebuild->mappingDigest === $described->revision,
        'Installed Search compilation did not include the owner field under its stable ID.');

    $actor = new ViewContext($tenant, 'system', $scope->principalId);
    $viewScope = new ViewScope($entity->value, null, null, null);
    $viewSchema = new FixtureSavedViewSchema($catalogue, $scope, $actor);
    $app->instance(SavedViewInventory::class, new FixtureSavedViewInventory($app, $scope, $actor, $viewScope));
    $app->instance(ViewSchemaProvider::class, $viewSchema);
    $app->instance(ViewAccessPolicy::class, new readonly class($actor, $viewScope) implements ViewAccessPolicy
    {
        public function __construct(private ViewContext $actor, private ViewScope $scope) {}

        public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
        {
            return $context === $this->actor && $subject->scope == $this->scope;
        }
    });
    $views = $app->make(SavedViewRepository::class);
    $filter = ['field' => $field->value, 'op' => 'eq', 'value' => 'PUMP-1'];
    $sort = [['field' => $field->value, 'direction' => 'asc']];
    $view = $views->save($actor, new SaveViewInput(null, '', $viewScope, 'table', ['en' => 'Pumps'], JsonDocuments::capture([
        'version' => 'saved-view/1', 'filters' => $filter, 'sorts' => $sort, 'columns' => [$field->value],
    ])));
    [$saved, $before] = $viewSchema->compile($views, $viewScope, $view->id);
    verifyFixture($saved->projection === [$field->value] && JsonDocuments::array($saved->filters) == $filter && $saved->sort == $sort
        && $before->field('asset_code')->id->value === $field->value && $viewSchema->schema($actor, $entity->value)->fields[0]->mappingKey === $field->value,
        'Packman did not compile the saved field ID across the view grammar.');

    $app->instance(FixtureSavedViewReferences::class, new FixtureSavedViewReferences($viewSchema, $views, $scope, $actor, $viewScope));
    $app->tag(FixtureSavedViewReferences::class, FieldSchemaDependencies::class);
    $definition = $before->fields[$field->value];
    $rename = FieldSchemaChange::revise(FieldDefinition::fromArray([
        ...$definition->toArray(), 'machine_key' => 'equipment_code', 'presentation' => ['label' => 'Equipment code'],
    ]));
    $renamedPlan = publishFieldSchema($app, $scope, $before, $rename, ['saved-view:'.$view->id]);
    [$renamed, $after] = $viewSchema->compile($views, $viewScope, $view->id);
    $retained = $views->viewsFor($actor, $viewScope);
    $renamedSearch = $app->make(SearchRebuilds::class)->plan($scope, new RebuildInput($entity));
    verifyFixture($before->revision->value !== $after->revision->value && $after->revision->value === $renamedPlan->target->value
        && $before->mappingDigest !== $after->mappingDigest && $after->field('equipment_code')->id->value === $field->value
        && $after->fields[$field->value]->presentation['label'] === 'Equipment code'
        && $rebuild->mappingDigest !== $renamedSearch->mappingDigest
        && $renamed->schemaVersion === $saved->schemaVersion && $renamed->projection === $saved->projection
        && JsonDocuments::array($renamed->filters) == $filter && $renamed->sort == $sort
        && count($retained) === 1 && $retained[0]->id === $view->id && $retained[0]->revision === $view->revision,
        'Publishing renamed field metadata changed the saved query identity or failed to bind the actual owner revision.');

    $app->make(SearchProofOwner::class)->hideCustomField();
    $masked = $catalogue->snapshot($scope, $entity);
    verifyFixture($masked->fields === [] && $masked->revision->value === $after->revision->value
        && $masked->mappingDigest === $after->mappingDigest && $projection->describe($context)->revision === $renamedSearch->mappingDigest,
        'Field masking changed the structural schema witness.');

    try {
        $viewSchema->compile($views, $viewScope, $view->id);

        throw new LogicException('Packman compiled the masked field.');
    } catch (SavedViewException $error) {
        $maskedError = $error->errorDescriptor();
        verifyFixture($maskedError->errorCode === PanelErrorCode::InvalidSavedView, 'Unexpected masked saved-view outcome.');
    }

    return [
        'driver' => get_class($driver), 'compiled_fields' => array_column($described->fields, 'id'), 'field_id' => $field->value,
        'mapping' => $rebuild->mappingDigest, 'renamed_mapping' => $renamedSearch->mappingDigest,
        'schema_revision_before' => $before->revision->value, 'schema_revision_after' => $after->revision->value,
        'schema_mapping_before' => $before->mappingDigest, 'schema_mapping_after' => $after->mappingDigest,
        'view_id' => $view->id, 'view_revision' => $view->revision, 'view_grammar' => $saved->schemaVersion,
        'view_projection' => $renamed->projection, 'view_filter' => JsonDocuments::array($renamed->filters), 'view_sort' => $renamed->sort,
        'dependencies' => $renamedPlan->dependencies, 'old_key' => $definition->machineKey, 'old_label' => $definition->presentation['label'],
        'new_key' => $after->fields[$field->value]->machineKey, 'masked_rejected' => true,
        'new_label' => $after->fields[$field->value]->presentation['label'], 'masked_error' => $maskedError->code,
    ];
}

function publishFieldSchema(Application $app, DataScope $scope, FieldSchemaSnapshot $before, FieldSchemaChange $change, array $dependencies = []): SchemaPlan
{
    $schemas = $app->make(FieldSchemas::class);
    $plan = $schemas->plan($scope, $change, $before->revision);
    verifyFixture($plan->dependencies === $dependencies && $plan->blockers === [], 'The field operation did not resolve the expected saved dependencies.');
    $operation = $schemas->apply($scope, $plan->ref, $plan->digest, new OperationKey('fixture-field-'.$change->kind, 1));
    $kernel = $app->make(Kernel::class);
    verifyFixture($kernel->call('corex:custom-fields:work', ['--limit' => 12]) === 0, $kernel->output());
    $status = $schemas->inspect($scope, $operation);
    verifyFixture($status->state === 'completed', 'The field schema operation did not finish: '.json_encode($status, JSON_THROW_ON_ERROR));

    return $plan;
}
