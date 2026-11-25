<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use App\Scenario\ScenarioPrincipalAuthority;
use App\Scenario\ScenarioSearchSchema;
use App\Scenario\SearchScenarioMeili;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\OperationKey;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Search\Index\Contracts\IndexNamespaceExclusion;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\RebuildInput;
use CoreX\Search\Rebuild\Data\RebuildRef;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NativeSearch
{
    private const string OWNER = 'axiomasoft/corex-search';

    public static function run(Scenario $proof): array
    {
        app()->bind(SearchSource::class, ScenarioSearchSchema::class);
        app()->tag(ScenarioSearchSchema::class, SearchProjectionContributor::class);
        app()->bind(FieldUsePolicy::class, SearchCorpusGrants::class);
        app()->bind(DataScopeAuthorizer::class, SearchCorpusGrants::class);

        return Inventory::enter(Scenario::ACCOUNT_B, null, static function () use ($proof): array {
            DB::statement('CREATE TABLE scenario_service_grants(service text PRIMARY KEY, account uuid NOT NULL)');
            DB::statement('CREATE TABLE scenario_index_fields(scope text, entity text, field text, PRIMARY KEY(scope,entity,field))');
            DB::table('scenario_service_grants')->insert(['service' => SearchCorpusGrants::SERVICE, 'account' => Scenario::ACCOUNT_B]);

            return resolve(ScenarioPrincipalAuthority::class)->forService(resolve(TenantContextResolver::class)->current(), SearchCorpusGrants::SERVICE, static function () use ($proof): array {
                $scope = Inventory::scope();
                foreach (['record', 'marker', 'tenant_a_label', 'tenant_b_label'] as $field) {
                    DB::table('scenario_index_fields')->insert(['scope' => hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)),
                        'entity' => ScenarioSearchSchema::ENTITY, 'field' => $field]);
                }
                $first = self::publish($scope, self::start($scope, 'initial'));
                $second = self::publish($scope, self::start($scope, 'replacement'));
                $rebuilds = resolve(SearchRebuilds::class);
                $ref = self::start($scope, 'interrupted');
                $third = $rebuilds->inspect($scope, $ref)->destination;
                $gen = DB::table('corex_search_generations')->where('generation', $third)->sole();
                $target = new IndexTarget($scope, ScenarioSearchSchema::entity(), 'meilisearch', $gen->cluster, $third, $gen->uid, $gen->mapping_digest, $gen->allocation_id);
                $submission = SearchScenarioMeili::await('the original native create ACK', static function () use ($scope, $third, $target): ?object {
                    $ack = DB::table('corex_search_submissions')->where(['uid' => $target->uid, 'state' => 'accepted'])->first();

                    if ($ack === null) {
                        resolve(IndexWorker::class)->advance($scope, $target->entity, $third);
                    }

                    return $ack;
                });
                $key = resolve(MeilisearchCredentialProvider::class)->forTarget($target, MeilisearchAccess::Observe);
                $task = Http::baseUrl(getenv('MEILISEARCH_HOST'))->withToken($key->reveal())->timeout(3)
                    ->get('/tasks/'.explode('.', $submission->task_ref, 2)[0])->throw()->json();
                $proof->same([$target->uid, 'settingsUpdate'], [$task['indexUid'], $task['type']], 'The native pending submission has its original scoped Meili task');
                $original = DB::table('corex_search_generations')->where('generation', $first)->sole();
                $proof->check($original->state === 'retained' && $original->retain_until !== null && $second !== $first, 'The native publisher retains the replaced physical generation');
                $scan = self::scan($scope);
                $proof->check(in_array($submission->task_ref, $scan['search-task'] ?? [], true), 'The production Search owner names the unsettled task in its exact scope');
                $proof->check(in_array($original->uid, $scan['search-generation'] ?? [], true), 'The production Search owner names the retained UID');
                $terminal = SearchScenarioMeili::await('native task settlement', static function () use ($scope, $third, $target, $submission): ?object {
                    $row = DB::table('corex_search_submissions')->where('submission_id', $submission->submission_id)->sole();

                    if ($row->state === 'confirmed' && $row->control_recorded) {
                        return $row;
                    }
                    resolve(IndexWorker::class)->advance($scope, $target->entity, $third);

                    return null;
                });
                $proof->same('cancelled', $rebuilds->cancel($scope, $ref)->state, 'Native cancellation retires the original destination');
                $supplied = self::scan($scope);
                $proof->check(in_array($target->uid, $supplied['retire-index'] ?? [], true), 'The Search owner offers a typed action for the retired destination');
                // This process now lacks the host port. A prior scan cannot confer cleanup authority.
                app()->offsetUnset(IndexNamespaceExclusion::class);
                $missing = self::scan($scope);
                $proof->check(in_array($target->uid, $missing['restore-exact-index-namespace-exclusion'] ?? [], true)
                    && ! in_array($target->uid, $missing['retire-index'] ?? [], true), 'A retired native UID without the configured namespace exclusion remains blocked');
                $proof->same($submission->task_ref, $terminal->task_ref, 'Reconciliation settled the original native receipt');

                return ['scope' => $scope, 'retained' => $original, 'replacement' => $second, 'target' => $target, 'pending' => $submission,
                    'task' => $task, 'scan' => $scan, 'settled' => $terminal, 'retired' => $supplied, 'unavailable' => $missing];
            });
        });
    }

    private static function start(DataScope $scope, string $key): RebuildRef
    {
        $owner = resolve(SearchRebuilds::class);
        $plan = $owner->plan($scope, new RebuildInput(ScenarioSearchSchema::entity()));

        return $owner->start($scope, $plan->ref, new OperationKey('lifecycle.'.$key, 1));
    }

    private static function publish(DataScope $scope, RebuildRef $ref): int
    {
        $owner = resolve(SearchRebuilds::class);
        $status = $owner->inspect($scope, $ref);
        SearchScenarioMeili::await('the installed indexer scans the physical B corpus', static function () use ($scope, $ref, $owner, $status): ?bool {
            if ($owner->inspect($scope, $ref)->state === 'catching-up') {
                return true;
            }
            resolve(IndexWorker::class)->advance($scope, ScenarioSearchSchema::entity(), $status->destination);

            return null;
        });
        $worker = resolve(RebuildWorker::class);
        $worker->barrier($scope, $ref);
        $published = $worker->publish($scope, $ref);

        if ($published->state !== 'published') {
            throw new RuntimeException('The native search pointer did not publish.');
        }

        return $published->destination;
    }

    private static function scan(DataScope $scope): array
    {
        $owner = array_find(iterator_to_array(app()->tagged(ModuleReferences::class)), static fn ($r) => $r->owner() === self::OWNER);
        $cursor = null;
        $ids = [];
        do {
            $page = $owner->scan(new ModuleRef(self::OWNER), $scope, $cursor);
            foreach ($page->blockers as $block) {
                if ($block->owner !== self::OWNER || $block->scope != $scope) {
                    throw new RuntimeException('The Search owner returned foreign evidence.');
                }
                $ids[$block->resourceKind][] = $block->resourceId;
                $ids[$block->requiredAction][] = $block->resourceId;
            }
            foreach ($page->actions as $action) {
                if ($action->owner !== self::OWNER || $action->namespace !== getenv('COREX_SCENARIO_MEILI_NAMESPACE')) {
                    throw new RuntimeException('The Search owner offered a foreign action.');
                }
                $ids[$action->resourceKind][] = $action->resourceId;
            }
            $cursor = $page->next;
        } while ($cursor !== null);

        return $ids;
    }
}
