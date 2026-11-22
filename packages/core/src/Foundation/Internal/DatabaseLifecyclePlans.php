<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Features\Data\FeatureFlagDefinition;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Contracts\ReferenceCleanup;
use CoreX\Foundation\Contracts\ReferenceExclusion;
use CoreX\Foundation\Data\CleanupReceipt;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\LifecycleConfirmation;
use CoreX\Foundation\Data\LifecyclePlan;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\LifecycleStatus;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Exceptions\LifecyclePlanConflict;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Psr\Clock\ClockInterface;
use Throwable;

#[Internal]
final class DatabaseLifecyclePlans implements LifecyclePlans
{
    private const string Table = 'sys_lifecycle_plans';

    /** @var array{string,string,list<bool|string>}|null */
    private ?array $transition = null;

    /** @var ?Closure():void */
    private ?Closure $verifyLock = null;

    /** @param Closure(DataScope,Connection):((Closure():void)|null) $commitConfirmation */
    public function __construct(private readonly Application $app, private readonly DatabaseManager $database, private readonly CurrentWorkScope $current, private readonly LifecycleOperator $operator, private readonly DatabaseScopeControl $control, private readonly DataScopeCodec $codec, private readonly LifecycleCatalogue $catalogue, private readonly ModuleRegistry $registry, private readonly ModuleDiscovery $discovery, private readonly ClockInterface $clock, private readonly ExceptionHandler $exceptions, private readonly Closure $commitConfirmation, private readonly ?string $packmanConnection = null) {}

    public function plan(DataScope $scope, ModuleId $module, LifecycleAction $action): LifecyclePlan
    {
        return $this->prepare(scope: $scope, module: $module, action: $action, reason: DisableReason::Manual, cascade: false);
    }

    public function inspect(DataScope $scope, LifecyclePlanRef $ref): LifecycleStatus
    {
        $row = $this->row(scope: $scope, ref: $ref);
        $this->operator->authorize(scope: $scope, target: new ModuleRef($row->module), ability: 'inspect', ref: $ref, digest: new PlanDigest($row->digest));

        return $this->status($row);
    }

    public function direct(string $module, LifecycleAction $action, DisableReason $reason = DisableReason::Manual, bool $cascade = false): void
    {
        $scope = $this->operator->current();
        $plan = $this->prepare(scope: $scope, module: new ModuleId($module), action: $action, reason: $reason, cascade: $cascade);

        if ($this->apply(scope: $scope, ref: $plan->ref, digest: $plan->digest)->phase !== LifecyclePhase::Done) {
            throw new LifecycleBlocked('lifecycle-blocked '.$plan->ref->id);
        }
    }

    public function confirmedPurge(string $module, LifecycleConfirmation $approval): void
    {
        $scope = $this->operator->current();
        $row = $this->row(scope: $scope, ref: $approval->ref);

        if ($row->module !== $module || $row->action !== LifecycleAction::Purge->value) {
            throw new LifecyclePlanConflict('Purge confirmation does not select this module.');
        }

        if ($this->apply(scope: $scope, ref: $approval->ref, digest: $approval->digest)->phase !== LifecyclePhase::Done) {
            throw new LifecycleBlocked('lifecycle-blocked '.$approval->ref->id);
        }
    }

    /** @param list<bool|string> $arguments */
    public function consumeTransition(string $module, string $method, array $arguments): bool
    {
        if ($this->transition !== [$module, $method, $arguments]) {
            return false;
        }
        $this->transition = null;

        return true;
    }

    public function activate(ModuleLifecycle $inner, GuardedModuleLifecycle $guard, string $module, string $method, string $edition = 'standard', bool $cascade = false): void
    {
        $scope = $this->operator->current();
        $target = new ModuleRef($module);
        $this->operator->authorize(scope: $scope, target: $target, ability: $method);
        $this->locked(scope: $scope, work: function () use ($inner, $guard, $scope, $module, $method, $edition, $cascade): void {
            $targets = [$module];

            if ($method === 'enable') {
                for ($i = 0; isset($targets[$i]); $i++) {
                    foreach ($this->registry->get($targets[$i])->requires as $dependency) {
                        if ($dependency['kind'] === 'module' && ! in_array($dependency['package'], $targets, true)) {
                            $targets[] = $dependency['package'];
                        }
                    }
                }
            }
            $closures = [];
            foreach ($targets as $name) {
                $member = new ModuleRef($name);
                $closed = $this->control->moduleClosure(scope: $scope, target: $member);

                if ($name !== $module && (! $cascade || $inner->status($name)->state === ModuleState::Enabled)) {
                    // Even a satisfied prerequisite is a new dependency reference of this enable.
                    if ($closed !== null) {
                        throw new LifecycleBlocked('A prerequisite is closed to new dependents.');
                    }

                    continue;
                }
                $this->operator->authorize(scope: $scope, target: $member, ability: $method);

                if ($closed !== null) {
                    $record = $this->row(scope: $scope, ref: new LifecyclePlanRef($closed));

                    if ($record->state !== 'done' || $method === 'upgrade') {
                        throw new LifecycleBlocked('Another lifecycle plan still owns this module.');
                    }
                    $closures[$name] = $closed;
                }
            }
            $db = $this->owner($scope);
            $db->transaction(function () use ($inner, $guard, $module, $method, $edition, $cascade, $scope, $db, $closures): void {
                $this->current->assert(scope: $scope, connection: $db);
                $args = $method === 'enable' ? [$module, $edition, $cascade] : [$module];
                $frame = $this->app->make(LifecycleFrame::class);

                if ($method === 'enable' && $closures !== []) {
                    $frame->admitManifestDefault = function (FeatureFlagDefinition $definition) use ($scope, $db, $closures): ?string {
                        $name = $definition->module;

                        if ($name === null || ! isset($closures[$name]) || $db->transactionLevel() === 0) {
                            return null;
                        }
                        $this->current->assert(scope: $scope, connection: $db);
                        $this->operator->authorize(scope: $scope, target: new ModuleRef($name), ability: 'enable');

                        if ($this->control->moduleClosure(scope: $scope, target: new ModuleRef($name)) !== $closures[$name]) {
                            throw new LifecycleBlocked('Native declaration lost its module closure.');
                        }
                        foreach ($this->registry->compiled()->featureFlags as $flag) {
                            if ($flag->module === $name && $flag->key === $definition->key && $flag->default === $definition->default
                                && $flag->payload?->canonicalJson === $definition->payload?->canonicalJson) {
                                return $closures[$name];
                            }
                        }

                        return null;
                    };
                }

                try {
                    $frame->invoke($guard, $method, $args, fn () => match ($method) {
                        'enable' => $inner->enable($module, $edition, $cascade),
                        'repair' => $inner->repair($module),
                        'upgrade' => $inner->upgrade($module),
                        default => throw new LifecycleBlocked('Unknown module transition.'),
                    });
                } finally {
                    $frame->admitManifestDefault = null;
                }
            });

            if ($method === 'enable') {
                foreach ($closures as $name => $closed) {
                    $this->operator->authorize(scope: $scope, target: new ModuleRef($name), ability: $method);

                    if ($inner->status($name)->state === ModuleState::Enabled) {
                        $this->control->reopenModule(scope: $scope, target: new ModuleRef($name), expected: $closed);
                    }
                }
            }
        });
    }

    /** @return non-empty-list<string> */
    private function disableTargets(string $module, bool $cascade): array
    {
        // Native Packman dispatch discovers manifests at transition time.
        $manifests = $this->discovery->discover();
        $targets = [$module];
        for ($i = 0; isset($targets[$i]); $i++) {
            foreach ($manifests as $manifest) {
                if (in_array($manifest->composerName, $targets, true)) {
                    continue;
                }
                foreach ($manifest->requires as $dependency) {
                    if ($dependency->kind !== 'module' || $dependency->package !== $targets[$i]) {
                        continue;
                    }

                    if ($this->lifecycle()->status($manifest->composerName)->state === ModuleState::Enabled) {
                        if (! $cascade) {
                            throw new LifecycleBlocked('An enabled dependent requires a cascading disable.');
                        }
                        $targets[] = $manifest->composerName;
                    }

                    break;
                }
            }
        }

        return $targets;
    }

    /** @param array{reason:string,cascade:bool,targets:list<string>} $args */
    private function assertTargets(DataScope $scope, ModuleRef $target, LifecycleAction $action, LifecyclePlanRef $ref, PlanDigest $digest, array $args): void
    {
        $saved = $args['targets'];

        if ($action === LifecycleAction::Disable) {
            $live = $this->disableTargets(module: $target->package, cascade: $args['cascade']);
            sort($live, SORT_STRING);
            sort($saved, SORT_STRING);

            if ($live !== $saved) {
                throw new LifecyclePlanConflict('The current Packman cascade differs from the approved fenced modules.');
            }
        }
        foreach ($saved as $name) {
            $member = new ModuleRef($name);
            $this->operator->authorize(scope: $scope, target: $member, ability: $action->value, ref: $ref, digest: $digest);

            if ($this->control->moduleClosure(scope: $scope, target: $member) !== $ref->id) {
                throw new LifecycleBlocked('The original module closure was lost.');
            }
        }
    }

    private function prepare(DataScope $scope, ModuleId $module, LifecycleAction $action, DisableReason $reason, bool $cascade): LifecyclePlan
    {
        $target = new ModuleRef($module->package);
        $this->operator->authorize(scope: $scope, target: $target, ability: $action->value);

        return $this->locked(scope: $scope, work: function () use ($scope, $module, $target, $action, $reason, $cascade): LifecyclePlan {
            $declaration = $this->registry->get($module->package);
            $state = $this->lifecycle()->status($module->package)->state;

            if ($action !== LifecycleAction::Disable && ! in_array($state, [ModuleState::Disabled, ModuleState::Archived], true)) {
                throw new LifecycleBlocked('Detach and purge require a disabled or archived module.');
            }
            $targets = $action === LifecycleAction::Disable ? $this->disableTargets(module: $module->package, cascade: $cascade) : [$module->package];
            foreach ($targets as $name) {
                $this->operator->authorize(scope: $scope, target: new ModuleRef($name), ability: $action->value);
            }
            $args = ['reason' => $reason->value, 'cascade' => $cascade, 'targets' => $targets];
            $previous = $this->control->moduleClosure(scope: $scope, target: $target);
            $prior = $previous === null ? null : $this->row(scope: $scope, ref: new LifecyclePlanRef($previous));

            if ($prior !== null && $prior->state !== 'done' && ($prior->module !== $module->package || $prior->action !== $action->value || self::hash($prior->arguments) !== self::hash($args))) {
                throw new LifecycleBlocked('Another plan owns this module closure.');
            }
            $db = $this->owner($scope);
            $query = $db->table(self::Table)->where($this->logical($scope))->where('module', $module->package);

            if ($prior !== null && $prior->state === 'done' && $prior->action === $action->value && self::hash($prior->arguments) === self::hash($args)) {
                return new LifecyclePlan(ref: new LifecyclePlanRef($prior->planId), module: $module, action: $action, digest: new PlanDigest($prior->digest));
            }
            $generation = $previous ?? (clone $query)->where('state', 'done')->orderByDesc('plan_id')->value('plan_id');
            $request = self::hash([$scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->restoreEpoch, $action, $args, $generation]);
            $id = $prior !== null && $prior->state !== 'done' ? $prior->planId : (clone $query)->where('request_key', $request)->value('plan_id');

            if ($id === null) {
                $id = (string) Str::uuid7();
                $db->table(self::Table)->insert([...$this->logical($scope), 'module' => $module->package, 'plan_id' => $id, 'request_key' => $request,
                    'scope' => self::encode($this->codec->encode($scope)), 'state' => 'planned', 'action' => $action->value, 'digest' => $request, 'arguments' => self::encode($args),
                    'inventory' => '[]', 'blockers' => '[]', 'actions' => '[]', 'leases' => '[]', 'cursor' => '[]', 'receipts' => '[]', 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            } elseif (! is_string($id)) {
                throw new LifecycleBlocked('Invalid stored lifecycle plan ID.');
            }
            $ref = new LifecyclePlanRef($id);
            foreach ($args['targets'] as $name) {
                $member = new ModuleRef($name);
                $old = $this->control->moduleClosure(scope: $scope, target: $member);

                if ($old !== null && $old !== $id && $this->row(scope: $scope, ref: new LifecyclePlanRef($old))->state !== 'done') {
                    throw new LifecycleBlocked('A dependent module is fenced by another plan.');
                }
                $this->control->fenceModule(scope: $scope, target: $member, plan: $id, predecessor: $old);
            }
            $record = $this->row(scope: $scope, ref: $ref);

            if (! $record->cleanupStarted && $record->receipts === [] && ! in_array($record->state, ['cleaning', 'packman-transition'], true)) {
                $this->write(ref: $ref, changes: ['state' => 'fenced']);
                $scan = $this->scan(scope: $scope, ref: $ref, action: $action, targets: $args['targets']);
                $digest = self::hash([$record->requestKey, $this->codec->encode($scope), $declaration->toArray(), $scan['inventory'], $scan['actions'], $args, $action]);
                $this->write(ref: $ref, changes: ['digest' => $digest, 'inventory' => self::encode($scan['inventory']), 'actions' => self::encode($scan['actions']),
                    'blockers' => self::encode($scan['blockers']), 'state' => $scan['blockers'] === [] ? 'fenced' : 'blocked']);
            }

            return new LifecyclePlan(ref: $ref, module: $module, action: $action, digest: new PlanDigest($this->row(scope: $scope, ref: $ref)->digest));
        });
    }

    public function apply(DataScope $scope, LifecyclePlanRef $ref, PlanDigest $digest): LifecycleStatus
    {
        $initial = $this->row(scope: $scope, ref: $ref);
        $target = new ModuleRef($initial->module);
        $this->operator->authorize(scope: $scope, target: $target, ability: $initial->action, ref: $ref, digest: $digest);
        $this->operator->authorize(scope: $scope, target: $target, ability: 'inspect', ref: $ref, digest: $digest);

        if (! hash_equals($initial->digest, $digest->value)) {
            throw new LifecyclePlanConflict('Lifecycle plan digest changed.');
        }

        return $this->locked(scope: $scope, work: function () use ($scope, $ref, $digest): LifecycleStatus {
            $row = $this->row(scope: $scope, ref: $ref);
            $action = LifecycleAction::from($row->action);
            $target = new ModuleRef($row->module);
            $this->operator->authorize(scope: $scope, target: $target, ability: $action->value, ref: $ref, digest: $digest);
            $this->operator->authorize(scope: $scope, target: $target, ability: 'inspect', ref: $ref, digest: $digest);

            if (! hash_equals($row->digest, $digest->value)) {
                throw new LifecyclePlanConflict('Lifecycle plan digest changed.');
            }

            if ($row->state === 'done') {
                $this->release(scope: $scope, ref: $ref);

                return $this->status($row);
            }
            $args = $row->arguments;
            $targets = $args['targets'];
            $this->assertTargets(scope: $scope, target: $target, action: $action, ref: $ref, digest: $digest, args: $args);
            $scan = $this->scan(scope: $scope, ref: $ref, action: $action, targets: $targets);

            if ($scan['blockers'] !== []) {
                $this->write(ref: $ref, changes: ['state' => 'blocked', 'blockers' => self::encode($scan['blockers'])]);

                return $this->status($this->row(scope: $scope, ref: $ref));
            }
            $approved = $row->actions;

            if (self::hash($scan['inventory']) !== self::hash($row->inventory) || array_diff_key($scan['actions'], $approved) !== []) {
                throw new LifecyclePlanConflict('Review the changed owner inventory and cleanup schedule.');
            }
            foreach ($scan['actions'] as $key => $entry) {
                if (self::hash($entry) !== self::hash($approved[$key])) {
                    throw new LifecyclePlanConflict('Cleanup identity changed.');
                }
            }

            $confirmCommit = null;

            try {
                if ($action === LifecycleAction::Purge) {
                    $this->clean(scope: $scope, ref: $ref, digest: $digest, target: $target, approved: $approved);
                    $final = $this->scan(scope: $scope, ref: $ref, action: $action, targets: $targets);

                    if ($final['blockers'] !== []) {
                        $this->write(ref: $ref, changes: ['state' => 'blocked', 'blockers' => self::encode($final['blockers'])]);

                        return $this->status($this->row(scope: $scope, ref: $ref));
                    }

                    if (self::hash($final['inventory']) !== self::hash($scan['inventory']) || array_diff_assoc(array_map(self::hash(...), $final['actions']), array_map(self::hash(...), $approved)) !== []) {
                        throw new LifecyclePlanConflict('The final owner inventory or cleanup identity changed.');
                    }
                    $this->clean(scope: $scope, ref: $ref, digest: $digest, target: $target, approved: $approved, verifyOnly: true);
                }
                $this->write(ref: $ref, changes: ['state' => 'packman-transition', 'blockers' => '[]']);
                $this->verifyLeases(scope: $scope, ref: $ref);
                $db = $this->owner($scope);
                $db->transaction(function () use ($db, $scope, $target, $action, $ref, $digest, $args, &$confirmCommit): void {
                    $this->current->assert(scope: $scope, connection: $db);
                    $confirmCommit = ($this->commitConfirmation)($scope, $db);
                    $this->assertTargets(scope: $scope, target: $target, action: $action, ref: $ref, digest: $digest, args: $args);
                    $keep = $action === LifecycleAction::Detach;
                    $executor = $this->app->make(self::class);
                    $executor->transition = $action === LifecycleAction::Disable
                        ? [$target->package, 'disable', [$args['reason'], $args['cascade']]]
                        : [$target->package, 'uninstall', [$keep]];

                    try {
                        $lifecycle = $this->lifecycle();

                        $probe = $action === LifecycleAction::Purge ? ($this->probes()[$target->package] ?? null) : null;
                        $tables = $probe instanceof ModuleTableReferences ? $probe->moduleTables() : [];
                        // PostgreSQL retains each relation lock through the native DDL and the done checkpoint.
                        $namespace = $tables === [] ? null : $this->schemaNamespace($db);
                        foreach ($tables as $table) {
                            $db->table($table)->useWritePdo()->limit(0)->get();
                        }

                        if ($action === LifecycleAction::Disable) {
                            $lifecycle->disable($target->package, DisableReason::from($args['reason']), $args['cascade']);
                        } else {
                            $lifecycle->uninstall($target->package, keepData: $keep);
                        }
                        $result = $lifecycle->status($target->package)->state;
                        $changes = ['state' => 'done', 'transition_result' => $result->value];

                        if ($tables !== [] && $result === ModuleState::Purged && $this->schemaNamespace($db) === $namespace && $this->relationsAbsent(db: $db, tables: $tables)) {
                            $original = $this->row(scope: $scope, ref: $ref);
                            $changes['cursor'] = self::encode(['last_page' => $original->cursor, 'schema' => $namespace, 'dropped_tables' => $tables,
                                'cleanup_digest' => self::hash([$original->actions, $original->receipts])]);
                        }
                        $this->write(ref: $ref, changes: $changes);
                    } finally {
                        $executor->transition = null;
                    }
                });
            } catch (Throwable $error) {
                // A synchronous afterCommit subscriber can throw after the native state and checkpoint are durable.
                if ($this->owner($scope)->transactionLevel() !== 0) {
                    throw $error;
                }
                $checkpoint = $this->row(scope: $scope, ref: $ref);

                if ($checkpoint->state === 'done' && hash_equals($checkpoint->digest, $digest->value)) {
                    $this->exceptions->report($error);

                    if ($confirmCommit !== null) {
                        try {
                            $confirmCommit();
                        } catch (Throwable $settlementError) {
                            $this->exceptions->report($settlementError);
                        }
                    }

                    try {
                        $this->release(scope: $scope, ref: $ref);
                    } catch (Throwable $releaseError) {
                        // The remaining leases stay journalled for same-digest replay.
                        $this->exceptions->report($releaseError);
                    }

                    throw $error;
                }

                if ($error instanceof AuthorizationException || $error instanceof LifecyclePlanConflict) {
                    throw $error;
                }
                $this->exceptions->report($error);
                $blocker = $error instanceof UnavailableOwnerCheckpoint ? $error->blocker : new LifecycleBlocker(owner: $target->package, resourceKind: 'transition', resourceId: $ref->id, scope: $scope, requiredAction: 'reconcile-owner-checkpoint');
                $this->write(ref: $ref, changes: ['state' => 'blocked', 'blockers' => self::encode([$blocker])]);

                return $this->status($this->row(scope: $scope, ref: $ref));
            }
            $this->release(scope: $scope, ref: $ref);

            return $this->status($this->row(scope: $scope, ref: $ref));
        });
    }

    /** @param list<string> $targets
     * @return array{inventory:array<mixed>,actions:array<string,mixed>,blockers:list<LifecycleBlocker>}
     */
    private function scan(DataScope $scope, LifecyclePlanRef $ref, LifecycleAction $action, array $targets): array
    {
        $composer = null;
        $contributions = null;
        $activation = [];
        $directory = [];
        $owners = [];
        $revisions = [];
        $quarantined = [];
        $actions = [];
        $blockers = [];
        $owner = 'axiomasoft/corex';

        try {
            $host = $this->inventory();
            $purges = $this->nativePurges($scope);
            $composition = $this->catalogue->composition();
            $composer = self::hash($composition);
            $compiled = $this->registry->compiled();
            $contributions = $compiled->fingerprint;
            foreach ($targets as $package) {
                $activation[$package] = [$this->registry->get($package)->requires, $this->lifecycle()->status($package)->state->value];
            }
            foreach ($targets as $package) {
                $target = new ModuleRef($package);
                $outstanding = $this->control->outstandingReferences(scope: $scope, target: $target);
                foreach ($outstanding as $ticket) {
                    $blockers[] = new LifecycleBlocker(owner: $owner, resourceKind: 'admission-ticket', resourceId: $ticket, scope: $scope, requiredAction: 'drain-or-reconcile');
                }

                if ($outstanding !== []) {
                    continue;
                }
                $directory[$package] = $host->revision(operator: $scope, target: $target);
                $scopes = $host->scopes(operator: $scope, target: $target);

                if ($scopes === [] || $directory[$package] === '') {
                    throw new LifecycleBlocked('Physical scope roster unavailable.');
                }
                $seen = [];
                foreach ($scopes as $subject) {
                    $this->samePhysical(expected: $scope, actual: $subject);
                    $scopeKey = $subject->workspaceId === null ? 'account' : 'workspace:'.$subject->workspaceId;

                    if (isset($seen[$scopeKey])) {
                        throw new LifecycleBlocked('Duplicate physical scope.');
                    }
                    $seen[$scopeKey] = true;
                    $host->run(scope: $subject, operation: function () use ($host, $target, $subject, $composition, $purges, $ref, $scopeKey, $action, &$owner, &$owners, &$revisions, &$quarantined, &$actions, &$blockers): void {
                        $this->current->assert(scope: $subject, connection: $this->database->connection());
                        $probes = $this->probes();
                        $external = $host->owners(scope: $subject, target: $target);
                        $expected = array_unique([...$this->catalogue->expectedOwners($composition), ...$external, ...array_keys($probes)]);
                        sort($expected, SORT_STRING);
                        $owners[$target->package][$scopeKey] = $expected;
                        foreach ($expected as $probeOwner) {
                            $owner = $probeOwner;

                            if (! isset($probes[$owner])) {
                                $blockers[] = new LifecycleBlocker(owner: $owner, resourceKind: 'owner-inventory', resourceId: $owner, scope: $subject, requiredAction: 'install-or-restore-owner-probe');

                                continue;
                            }
                            $probe = $probes[$owner];

                            try {
                                if (in_array($owner, $external, true) && ! $probe instanceof ReferenceExclusion) {
                                    throw new LifecycleBlocked('Missing external writer exclusion.');
                                }

                                if ($probe instanceof ReferenceExclusion) {
                                    $this->hold(subject: $subject, target: $target, ref: $ref, probe: $probe, owner: $owner);
                                }
                                $cursor = null;
                                $revision = null;
                                $positions = [];
                                do {
                                    $frame = $this->app->make(LifecycleFrame::class);
                                    $previous = $frame->purges;
                                    $frame->purges = $purges;

                                    try {
                                        $page = $probe->scan($target, $subject, $cursor);
                                    } finally {
                                        $frame->purges = $previous;
                                    }

                                    if ($page->revision === '' || ($revision !== null && $page->revision !== $revision)) {
                                        throw new LifecycleBlocked('Owner revision changed during scan.');
                                    }
                                    $revision = $page->revision;
                                    foreach ($page->blockers as $blocker) {
                                        if (get_object_vars($blocker->scope) !== get_object_vars($subject) || $blocker->owner !== $owner) {
                                            throw new LifecycleBlocked('Invalid blocker provenance.');
                                        }

                                        if ($action === LifecycleAction::Disable && in_array($blocker->requiredAction, ['remove-or-rebind-reference', 'retain-original-receipt'], true)) {
                                            $quarantined[] = self::json(self::encode($blocker));
                                        } else {
                                            $blockers[] = $blocker;
                                        }
                                    }
                                    foreach ($page->actions as $item) {
                                        if ($item->owner !== $owner || ! $probe instanceof ReferenceCleanup) {
                                            throw new LifecycleBlocked('Unknown cleanup authority.');
                                        }

                                        if ($action === LifecycleAction::Purge) {
                                            $entry = ['scope' => $this->codec->encode($subject), 'action' => get_object_vars($item)];
                                            $key = self::hash([$owner, $scopeKey, $item->effectKey]);

                                            if (isset($actions[$key]) && $actions[$key] !== $entry) {
                                                throw new LifecycleBlocked('Conflicting cleanup key.');
                                            }
                                            $actions[$key] = $entry;
                                        }
                                    }
                                    $cursor = $page->next;

                                    if ($cursor !== null && ($cursor->revision !== $revision || isset($positions[$cursor->position]))) {
                                        throw new LifecycleBlocked('Unstable reference cursor.');
                                    }

                                    if ($cursor !== null) {
                                        $positions[$cursor->position] = true;
                                    }
                                    $this->write(ref: $ref, changes: ['cursor' => self::encode([$owner, $scopeKey, $target->package, $revision, $cursor])]);
                                } while ($cursor !== null);
                                $revisions[$target->package][$scopeKey][$owner] = $revision;
                            } catch (Throwable $error) {
                                $this->exceptions->report($error);
                                $blockers[] = new LifecycleBlocker(owner: $owner, resourceKind: 'owner-inventory', resourceId: $owner, scope: $subject, requiredAction: 'restore-complete-fenced-inventory');
                            }
                        }
                    });
                }

                if (! isset($seen['account'])) {
                    throw new LifecycleBlocked('Account-wide configuration was omitted.');
                }
            }
        } catch (Throwable $error) {
            $this->exceptions->report($error);
            $blockers[] = new LifecycleBlocker(owner: $owner, resourceKind: 'owner-inventory', resourceId: $owner, scope: $scope, requiredAction: 'restore-complete-fenced-inventory');
        }
        ksort($actions, SORT_STRING);

        return [
            'inventory' => [
                'composer' => $composer,
                'contributions' => $contributions,
                'activation' => $activation,
                'directory' => $directory,
                'owners' => $owners,
                'revisions' => $revisions,
                'quarantined' => $quarantined,
            ],
            'actions' => $actions,
            'blockers' => $blockers,
        ];
    }

    /** @return array<string,string> Current closure IDs with a publicly verified native purge. */
    private function nativePurges(DataScope $scope, ?string $owner = null): array
    {
        $db = $this->owner($scope);
        $query = $db->table(self::Table)->useWritePdo()->where($this->logical($scope))
            ->where(['state' => 'done', 'action' => 'purge', 'transition_result' => ModuleState::Purged->value])
            ->whereNotNull('cursor->dropped_tables');

        if ($owner !== null) {
            $query->where('module', $owner);
        }
        $purges = [];
        foreach ($query->get(['module', 'plan_id']) as $record) {
            $package = LifecycleCheckpoint::text(values: get_object_vars($record), key: 'module');
            $id = LifecycleCheckpoint::text(values: get_object_vars($record), key: 'plan_id');

            if ($this->control->moduleClosure(scope: $scope, target: new ModuleRef($package)) !== $id) {
                continue;
            }

            try {
                $lifecycle = $this->lifecycle();
                $purged = $owner === null
                    ? $this->confirmNativePurge(scope: $scope, ref: new LifecyclePlanRef($id), lifecycle: $lifecycle)
                    : $lifecycle->status($package)->state === ModuleState::Purged;

                if ($purged) {
                    $purges[$package] = $id;
                }
            } catch (AuthorizationException $error) {
                $this->exceptions->report($error);
            }
        }

        return $purges;
    }

    private function confirmNativePurge(DataScope $scope, LifecyclePlanRef $ref, ModuleLifecycle $lifecycle): bool
    {
        if ($scope->workspaceId !== null || $this->verifyLock === null) {
            throw new LifecycleBlocked('Purge provenance requires the locked account frame.');
        }
        $package = $this->row(scope: $scope, ref: $ref)->module;
        $frame = $this->app->make(LifecycleFrame::class);
        $this->transition = [$package, 'purge-status', []];
        $frame->purgeWitness = $this;

        try {
            return $lifecycle->status($package)->state === ModuleState::Purged;
        } finally {
            $frame->purgeWitness = null;
            $this->transition = null;
        }
    }

    /** @param list<string> $tables */
    public function droppedSchema(DataScope $scope, string $owner, array $tables, Connection $db): string
    {
        $purges = $this->app->make(LifecycleFrame::class)->purges ?? $this->nativePurges(scope: $scope, owner: $owner);
        $id = $purges[$owner] ?? null;

        if ($id === null || $this->owner($scope) !== $db || $this->control->moduleClosure(scope: $scope, target: new ModuleRef($owner)) !== $id) {
            throw new LifecycleBlocked('No current native purge for this physical reference owner.');
        }
        $row = $this->row(scope: $scope, ref: new LifecyclePlanRef($id));

        if ($row->module !== $owner || $row->action !== 'purge' || $row->state !== 'done' || $row->transitionResult !== ModuleState::Purged->value
            || $row->arguments['targets'] !== [$owner] || ($row->cursor['schema'] ?? null) !== $this->schemaNamespace($db)
            || ($row->cursor['dropped_tables'] ?? null) !== $tables || ! $this->relationsAbsent(db: $db, tables: $tables)
            || ($row->cursor['cleanup_digest'] ?? null) !== self::hash([$row->actions, $row->receipts])
            || count($row->receipts) !== count($row->actions)) {
            throw new LifecycleBlocked('Physical schema differs from the committed module purge.');
        }
        foreach ($row->actions as $key => $approved) {
            $receipt = LifecycleCheckpoint::map($row->receipts[$key] ?? null);
            LifecycleCheckpoint::text(values: $receipt, key: 'evidence');

            $original = LifecycleCheckpoint::cleanupAction(LifecycleCheckpoint::map($approved)['action'] ?? null);
            $confirmed = LifecycleCheckpoint::cleanupAction($receipt['action'] ?? null);

            if (get_object_vars($original) !== get_object_vars($confirmed)) {
                throw new LifecycleBlocked('Original purge obligation is unconfirmed.');
            }
        }

        return self::hash([$row->planId, $row->digest, $row->cursor, $row->receipts]);
    }

    /** Identify the actual database/schema on the writer, including the physical relation prefix. */
    private function schemaNamespace(Connection $db): string
    {
        $namespace = $db->scalar('select json_build_array(current_database(), current_schema(), current_schema()::regnamespace::oid)::text where current_schema() is not null', [], false);

        return is_string($namespace) && $namespace !== '' ? self::hash([$namespace, $db->getTablePrefix()]) : throw new LifecycleBlocked('Unknown PostgreSQL schema namespace.');
    }

    /** @param list<string> $tables */
    private function relationsAbsent(Connection $db, array $tables): bool
    {
        foreach ($tables as $table) {
            // Check every relation kind, including a view or a relation later on the search path.
            if ($db->scalar('select to_regclass(quote_ident(?))', [$db->getTablePrefix().$table], false) !== null) {
                return false;
            }
        }

        return true;
    }

    private function inventory(): LifecycleInventory
    {
        if (! $this->app->bound(LifecycleInventory::class)) {
            throw new LifecycleBlocked('Lifecycle physical inventory unavailable.');
        }

        return $this->app->make(LifecycleInventory::class);
    }

    /** @return array<string,ModuleReferences> */
    private function probes(): array
    {
        $probes = [];
        foreach ($this->app->tagged(ModuleReferences::class) as $probe) {
            if (! $probe instanceof ModuleReferences || isset($probes[$probe->owner()])) {
                throw new LifecycleBlocked('Duplicate or invalid owner probe.');
            }
            $probes[$probe->owner()] = $probe;
        }

        return $probes;
    }

    private function hold(DataScope $subject, ModuleRef $target, LifecyclePlanRef $ref, ReferenceExclusion $probe, string $owner): void
    {
        $row = $this->row(scope: $subject, ref: $ref);
        $leases = $row->leases;
        $key = self::hash([$target->package, $subject->accountId, $subject->workspaceId, $owner]);
        $old = isset($leases[$key]) ? LifecycleCheckpoint::lease(LifecycleCheckpoint::map($leases[$key])['lease'] ?? null) : null;
        $lease = $probe->hold(scope: $subject, module: $target, plan: $ref, previous: $old);

        if ($lease->owner !== $owner || $lease->namespace === '' || $lease->token === '' || $lease->revision === ''
            || ($old !== null && ($old->namespace !== $lease->namespace || $old->revision !== $lease->revision))) {
            throw new LifecycleBlocked('External exclusion changed.');
        }
        $probe->verify(scope: $subject, lease: $lease);
        $leases[$key] = ['scope' => $this->codec->encode($subject), 'lease' => get_object_vars($lease)];
        $this->write(ref: $ref, changes: ['leases' => self::encode($leases)]);
    }

    private function verifyLeases(DataScope $scope, LifecyclePlanRef $ref, bool $release = false): void
    {
        $leases = $this->row(scope: $scope, ref: $ref)->leases;
        foreach ($leases as $key => $saved) {
            $saved = LifecycleCheckpoint::map($saved);
            $subject = $this->codec->decode(LifecycleCheckpoint::map($saved['scope'] ?? null));
            $lease = LifecycleCheckpoint::lease($saved['lease'] ?? null);

            try {
                $this->inventory()->run(scope: $subject, operation: function () use ($subject, $lease, $release): void {
                    $probe = $this->probes()[$lease->owner] ?? null;

                    if (! $probe instanceof ReferenceExclusion) {
                        throw new LifecycleBlocked('External exclusion holder unavailable.');
                    }

                    if ($release) {
                        $probe->release($subject, $lease);
                    } else {
                        $probe->verify($subject, $lease);
                    }
                });

            } catch (AuthorizationException|LifecyclePlanConflict $error) {
                throw $error;
            } catch (Throwable $error) {
                throw new UnavailableOwnerCheckpoint(blocker: new LifecycleBlocker(owner: $lease->owner, resourceKind: 'owner-lease', resourceId: $lease->namespace, scope: $subject, requiredAction: 'restore-original-owner-exclusion'), cause: $error);
            }

            if ($release) {
                unset($leases[$key]);
                $this->write(ref: $ref, changes: ['leases' => self::encode($leases)]);
            }
        }
    }

    private function release(DataScope $scope, LifecyclePlanRef $ref): void
    {
        $this->verifyLeases(scope: $scope, ref: $ref, release: true);
    }

    /** @param array<string,mixed> $approved */
    private function clean(DataScope $scope, LifecyclePlanRef $ref, PlanDigest $digest, ModuleRef $target, array $approved, bool $verifyOnly = false): void
    {
        if (! $verifyOnly && $approved !== []) {
            $this->write(ref: $ref, changes: ['state' => 'cleaning', 'cleanup_started' => true]);
        }
        $receipts = $this->row(scope: $scope, ref: $ref)->receipts;
        foreach ($approved as $key => $entry) {
            $this->operator->authorize(scope: $scope, target: $target, ability: 'purge', ref: $ref, digest: $digest);
            $this->verifyLeases(scope: $scope, ref: $ref);
            $entry = LifecycleCheckpoint::map($entry);
            $subject = $this->codec->decode(LifecycleCheckpoint::map($entry['scope'] ?? null));
            $item = LifecycleCheckpoint::cleanupAction($entry['action'] ?? null);
            $leases = [];
            foreach ($this->row(scope: $scope, ref: $ref)->leases as $saved) {
                $saved = LifecycleCheckpoint::map($saved);
                $held = $this->codec->decode(LifecycleCheckpoint::map($saved['scope'] ?? null));

                if (get_object_vars($held) === get_object_vars($subject)) {
                    $lease = LifecycleCheckpoint::lease($saved['lease'] ?? null);
                    $leases[$lease->owner] = $lease;
                }
            }

            try {
                $receipt = $this->inventory()->run(scope: $subject, operation: function () use ($subject, $item, $receipts, $key, $leases, $verifyOnly): CleanupReceipt {
                    $probe = $this->probes()[$item->owner] ?? null;

                    if (! $probe instanceof ReferenceCleanup) {
                        throw new LifecycleBlocked('Cleanup owner unavailable.');
                    }
                    $this->owner($subject);
                    $frame = $this->app->make(LifecycleFrame::class);
                    $frame->cleanup = [$subject, $item];
                    $frame->leases = $leases;

                    try {
                        $receipt = $probe->lookup($subject, $item);

                        if (isset($receipts[$key]) && ($receipt === null || $receipt->evidence !== LifecycleCheckpoint::text(values: LifecycleCheckpoint::map($receipts[$key]), key: 'evidence'))) {
                            throw new LifecycleBlocked('Previously recorded cleanup receipt unavailable.');
                        }

                        if ($receipt !== null) {
                            return $receipt;
                        }

                        if ($verifyOnly) {
                            throw new LifecycleBlocked('Final owner receipt unavailable.');
                        }
                        $probe->cleanup($subject, $item);

                        return $probe->lookup($subject, $item) ?? throw new LifecycleBlocked('Owner has not confirmed this cleanup.');
                    } finally {
                        $frame->cleanup = null;
                        $frame->leases = [];
                    }
                });

                if (! $receipt instanceof CleanupReceipt || get_object_vars($receipt->action) !== get_object_vars($item)) {
                    throw new LifecycleBlocked('Unexpected cleanup receipt.');
                }
            } catch (AuthorizationException|LifecyclePlanConflict $error) {
                throw $error;
            } catch (Throwable $error) {
                throw new UnavailableOwnerCheckpoint(blocker: new LifecycleBlocker(owner: $item->owner, resourceKind: $item->resourceKind, resourceId: $item->resourceId, scope: $subject, requiredAction: 'reconcile-original-cleanup-receipt'), cause: $error);
            }
            $receipts[$key] = ['action' => get_object_vars($item), 'evidence' => $receipt->evidence];
            $this->write(ref: $ref, changes: ['receipts' => self::encode($receipts)]);
        }
    }

    private function samePhysical(DataScope $expected, DataScope $actual): void
    {
        if ($expected->accountId !== $actual->accountId || $expected->accountIncarnation !== $actual->accountIncarnation
            || $expected->deploymentId !== $actual->deploymentId || $expected->bindingId !== $actual->bindingId
            || $expected->bindingGeneration !== $actual->bindingGeneration || $expected->restoreEpoch !== $actual->restoreEpoch) {
            throw new LifecycleBlocked('Lifecycle physical owner changed.');
        }
    }

    /** @template T
     * @param  Closure():T  $work
     * @return T
     */
    private function locked(DataScope $scope, Closure $work): mixed
    {
        if ($this->database->connection()->transactionLevel() !== 0) {
            throw new LifecycleBlocked('Lifecycle requires the owner outside a caller transaction.');
        }

        $this->owner($scope);

        return $this->control->withLifecycleLock(scope: $scope, work: function (Closure $verify) use ($work): mixed {
            $this->verifyLock = $verify;

            try {
                return $work();
            } finally {
                $this->verifyLock = null;
            }
        });
    }

    private function owner(DataScope $scope): Connection
    {
        if ($this->verifyLock !== null) {
            ($this->verifyLock)();
        }
        $db = $this->database->connection();

        if ($db->getDriverName() !== 'pgsql' || $db !== $this->database->connection($this->packmanConnection)) {
            throw new LifecycleBlocked('Lifecycle requires the verified PostgreSQL owner.');
        }
        $this->current->assert(scope: $scope, connection: $db);

        return $db;
    }

    private function lifecycle(): ModuleLifecycle
    {
        return $this->app->make(ModuleLifecycle::class);
    }

    /** @return array<string,string> */
    private function logical(DataScope $scope): array
    {
        return ['account_id' => $scope->accountId, 'account_incarnation' => $scope->accountIncarnation,
            'workspace_scope' => 'account'];
    }

    private function row(DataScope $scope, LifecyclePlanRef $ref): LifecycleCheckpoint
    {
        $db = $this->owner($scope);
        $row = $db->table(self::Table)->useWritePdo()->where($this->logical($scope))->where('plan_id', $ref->id)->first()
            ?? throw new LifecycleBlocked('Lifecycle plan unavailable.');
        $checkpoint = new LifecycleCheckpoint($row);
        $this->samePhysical(expected: $scope, actual: $this->codec->decode($checkpoint->scope));

        return $checkpoint;
    }

    /** @param array<string,mixed> $changes */
    private function write(LifecyclePlanRef $ref, array $changes): void
    {
        $scope = $this->operator->current();
        $this->row(scope: $scope, ref: $ref);

        if ($this->owner($scope)->table(self::Table)->where($this->logical($scope))->where('plan_id', $ref->id)->update([...$changes, 'updated_at' => $this->clock->now()]) !== 1) {
            throw new LifecycleBlocked('Lifecycle checkpoint unavailable.');
        }
    }

    private function status(LifecycleCheckpoint $row): LifecycleStatus
    {
        $blockers = [];
        foreach ($row->blockers as $b) {
            $b = LifecycleCheckpoint::map($b);
            $blockers[] = new LifecycleBlocker(owner: LifecycleCheckpoint::text(values: $b, key: 'owner'), resourceKind: LifecycleCheckpoint::text(values: $b, key: 'resource_kind'), resourceId: LifecycleCheckpoint::text(values: $b, key: 'resource_id'), scope: LifecycleCheckpoint::blockerScope($b['scope'] ?? null), requiredAction: LifecycleCheckpoint::text(values: $b, key: 'required_action'));
        }
        $receipts = [];
        foreach ($row->receipts as $r) {
            $r = LifecycleCheckpoint::map($r);
            $receipts[] = new CleanupReceipt(action: LifecycleCheckpoint::cleanupAction($r['action'] ?? null), evidence: LifecycleCheckpoint::text(values: $r, key: 'evidence'));
        }

        return new LifecycleStatus(ref: new LifecyclePlanRef($row->planId), phase: LifecyclePhase::from($row->state), digest: new PlanDigest($row->digest), blockers: $blockers, receipts: $receipts, transitionResult: $row->transitionResult);
    }

    /** @return array<mixed> */
    private static function json(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : throw new LifecycleBlocked('Invalid lifecycle checkpoint.');
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function hash(mixed $value): string
    {
        return hash('sha256', self::encode(self::ordered(json_decode(self::encode($value), true, flags: JSON_THROW_ON_ERROR))));
    }

    private static function ordered(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::ordered(...), $value);
    }
}
