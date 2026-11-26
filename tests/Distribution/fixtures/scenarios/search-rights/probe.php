<?php

declare(strict_types=1);

use App\Scenario\Scenario;
use App\Scenario\SearchScenarioData as Owner;
use App\Scenario\SearchScenarioMeili as Meili;
use App\Scenario\SearchScenarioRebuild as Rebuild;
use App\Scenario\SearchScenarioRecord;
use App\Scenario\SearchScenarioTrace;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Roles\RoleAdministration;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchHit;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Data\SearchSort;
use CoreX\Search\Query\Enums\StatisticStatus;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$mode = $argv[1] ?? '';
$trace = resolve(SearchScenarioTrace::class);

function role(string $workspace): string
{
    return Scenario::barrierRead('rights-ready')['roles'][$workspace];
}

function rightsQuery(DataScope $scope, array $options = []): SearchPage
{
    $args = array_replace(['fields' => ['title', 'secret'], 'limit' => 1], $options);

    return resolve(Search::class)->query($scope, new SearchQuery(Owner::entity(), ...$args));
}

function hits(SearchPage $page): array
{
    return array_map(static fn (SearchHit $hit): array => ['id' => $hit->record->id, 'values' => $hit->projection->values], $page->hits);
}

function denyBeforeBackend(Scenario $proof, SearchScenarioTrace $trace, Closure $query, string $use): void
{
    $trace->reset();

    try {
        $query();
    } catch (SearchFeatureUnavailable) {
        $proof->same([], $trace->backend, 'Revoked '.$use.' is rejected before a Meilisearch request');
        $proof->same([], $trace->loads, 'Revoked '.$use.' does not load an owner record');

        return;
    }

    throw new RuntimeException('Expected a typed failure for revoked '.$use.'.');
}

if ($mode === 'produce') {
    $database = Owner::install('search-rights');
    Owner::put($database, Owner::FIRST, 'shared equipment A first', 'private-a', 1);
    Owner::put($database, Owner::SECOND, 'shared equipment A second', 'private-a-second', 2);
    Owner::put($database, Owner::THIRD, 'shared equipment A third', 'private-a-third', 3);
    Owner::put($database, Owner::FIRST, 'shared equipment B first', 'private-b', 9, Scenario::W2);
    $roles = [];
    $targets = [];
    foreach ([Scenario::W1, Scenario::W2] as $workspace) {
        $roles[$workspace] = Owner::within(static function () use ($workspace): string {
            $roles = resolve(RoleAdministration::class);
            $id = $roles->create('fixture-search-'.$workspace, 'Authorized search reader');
            foreach (['read', 'search', 'filter', 'sort', 'aggregate', 'field.title.read', 'field.title.search', 'field.secret.read',
                'field.secret.search', 'field.secret.filter', 'field.visible_flag.filter', 'field.rank.read', 'field.rank.sort', 'field.rank.aggregate'] as $action) {
                $roles->setScope($id, SearchScenarioRecord::ENTITY, $action, Scope::Own);
            }
            $roles->assign($id, auth()->user(), $workspace);

            return $id;
        }, $workspace);
        $targets[$workspace] = Owner::within(static function (DataScope $scope) use ($proof, $workspace): IndexTarget {
            $ready = Rebuild::bootstrap($scope, 'rights-'.$workspace);
            $target = Rebuild::target($scope, $ready->destination);
            $expected = $workspace === Scenario::W1 ? 'shared equipment A first' : 'shared equipment B first';
            $proof->same($expected, Meili::value(Meili::document($target, Owner::FIRST), 'title'), 'Actual Meilisearch stores this workspace projection at the shared PK');

            return $target;
        }, $workspace, service: true);
    }
    $uids = array_map(static fn (IndexTarget $target): string => $target->uid, $targets);
    $proof->check(count(array_unique($uids)) === 2, 'The same record PK lives in two immutable UIDs on one Meilisearch cluster');
    $credential = resolve(MeilisearchCredentialProvider::class)->forTarget($targets[Scenario::W1], MeilisearchAccess::Search)->reveal();
    $provedCredential = Meili::admin()->withToken($credential);
    $proof->same(200, $provedCredential->post('/indexes/'.$uids[Scenario::W1].'/search', ['q' => ''])->status(), 'The issued key can perform its one allowed search');
    $proof->same(403, $provedCredential->post('/indexes/'.$uids[Scenario::W2].'/search', ['q' => ''])->status(), 'Meilisearch denies that key to the coincident PK in the other real index');
    Scenario::barrier('rights-ready', ['roles' => $roles, 'indexes' => $uids]);
    $proof->report($database, array_values($uids), [], ['indexes' => $uids, 'principal_roles' => $roles, 'credential_isolation' => true]);

    return;
}

switch ($mode) {
    case 'meili-rights':
        $targets = Scenario::barrierRead('rights-ready')['indexes'];
        $workspaceB = Owner::within(static fn (DataScope $scope): array => hits(rightsQuery($scope)), Scenario::W2);
        $proof->same([['id' => Owner::FIRST, 'values' => ['title' => 'shared equipment B first', 'secret' => 'private-b']]], $workspaceB, 'Workspace B sees only the B current projection for the same PK');
        $after = Owner::within(static function (DataScope $scope) use ($proof, $trace, $database): array {
            $baseline = rightsQuery($scope, ['count' => true]);
            $proof->same(Owner::FIRST, $baseline->hits[0]->record->id, 'The native candidate path starts in stable order');
            $proof->same('private-a', $baseline->hits[0]->projection->values['secret'], 'The initially granted field is present');
            $proof->same(StatisticStatus::Unavailable, $baseline->count->status, 'The candidate backend has no exact-count capability');
            $proof->same(null, $baseline->count->value, 'A candidate count exposes no numeric total');
            $proof->check(is_string($baseline->cursor) && $baseline->cursor !== '', 'An opaque continuation is returned for a multi-hit corpus');

            $search = ['text' => 'first', 'searchFields' => ['title'], 'fields' => ['title']];
            $proof->same([Owner::FIRST], array_column(hits(rightsQuery($scope, $search)), 'id'), 'A real native text query initially matches the old owner value');
            Owner::put($database, Owner::FIRST, 'fresh hydraulic label', 'private-a', 1);
            $proof->same([], hits(rightsQuery($scope, $search)), 'The previous indexed text stops matching after a current owner rename');
            $proof->same([Owner::SECOND], array_column(hits(rightsQuery($scope, [...$search, 'text' => 'second'])), 'id'), 'Unchanged authorized text still matches');
            Owner::put($database, Owner::FIRST, 'shared equipment A first', 'private-a', 1);

            $admin = resolve(RoleAdministration::class);
            $id = role(Scenario::W1);
            $admin->setScope($id, SearchScenarioRecord::ENTITY, 'field.secret.read', Scope::None);
            $hidden = rightsQuery($scope);
            $proof->same([['id' => Owner::FIRST, 'values' => ['title' => 'shared equipment A first']]], hits($hidden), 'Record visibility does not expose a separately revoked field');
            $proof->check(! str_contains(json_encode($hidden, JSON_THROW_ON_ERROR), 'private-a'), 'Hidden content is absent from hits, snippets and continuation');

            $admin->setScope($id, SearchScenarioRecord::ENTITY, 'field.secret.read', Scope::Own);
            foreach ([
                ['secret', FieldUse::Search, ['text' => 'private-a', 'searchFields' => ['secret']]],
                ['secret', FieldUse::Filter, ['filters' => [new SearchFilter('secret', ['private-a'])]]],
                ['rank', FieldUse::Sort, ['sort' => [new SearchSort('rank')]]],
                ['rank', FieldUse::Aggregate, ['facets' => ['rank']]],
            ] as [$field, $use, $options]) {
                $action = 'field.'.$field.'.'.$use->value;
                $request = new FieldUseRequest(new FieldSelection([$field]), $use);
                $proof->same(FieldUsePlanKind::ScopedPredicate, resolve(FieldUsePolicy::class)->plan($scope, Owner::entity(), $request)->kind, 'The native role originally grants this use');
                $admin->setScope($id, SearchScenarioRecord::ENTITY, $action, Scope::None);
                $proof->same(FieldUsePlanKind::Denied, resolve(FieldUsePolicy::class)->plan($scope, Owner::entity(), $request)->kind, 'The native role denies the revoked use itself');
                denyBeforeBackend($proof, $trace, static fn (): SearchPage => rightsQuery($scope, $options), $use->value);
                $proof->same('private-a', rightsQuery($scope)->hits[0]->projection->values['secret'], 'Field read authority remains independently available');
                $admin->setScope($id, SearchScenarioRecord::ENTITY, $action, Scope::Own);
            }

            $admin->setScope($id, SearchScenarioRecord::ENTITY, 'read', Scope::None);
            denyBeforeBackend($proof, $trace, static fn (): SearchPage => rightsQuery($scope), 'record read');
            $admin->setScope($id, SearchScenarioRecord::ENTITY, 'read', Scope::Own);

            $database->table('scenario_search_records')->where(['workspace_id' => Scenario::W1, 'id' => Owner::FIRST])->update(['owner_id' => Scenario::USER_B]);
            $changed = rightsQuery($scope, ['count' => true]);
            $proof->same(Owner::SECOND, $changed->hits[0]->record->id, 'Removing native ownership immediately removes the indexed hit');
            $proof->same(null, $changed->count->value, 'The revoked record cannot be recovered through a count');
            $proof->same('private-a-second', $changed->hits[0]->projection->values['secret'], 'The permitted record exposes only its own current projection');
            $continued = rightsQuery($scope, ['cursor' => $changed->cursor, 'count' => true]);
            $proof->same([Owner::THIRD], array_column(hits($continued), 'id'), 'The continuation traverses only remaining visible records');
            $database->table('scenario_search_records')->where(['workspace_id' => Scenario::W1, 'id' => Owner::FIRST])->update(['owner_id' => Scenario::USER_A]);

            return ['workspace_a' => hits($baseline), 'after_revoke' => hits($changed), 'next' => hits($continued), 'count' => $changed->count];
        });
        $proof->same([], Owner::blockingTickets(), 'Expected rights refusals leave the external account admission drainable');
        $proof->report($database, array_values($targets), ['workspace_b' => $workspaceB], $after);

        break;

    case 'postgres-rights':
        $result = Owner::within(static function (DataScope $scope) use ($proof): array {
            $page = rightsQuery($scope, ['count' => true, 'fields' => ['title']]);
            $proof->same(StatisticStatus::Exact, $page->count->status, 'Native PostgreSQL can prove one ACL and owner snapshot');
            $proof->same(3, $page->count->value, 'Exact count includes only the three authorized W1 records');
            $proof->same(Owner::FIRST, $page->hits[0]->record->id, 'PostgreSQL uses the normal installed search entry point');
            $proof->same(['title' => 'shared equipment A first'], $page->hits[0]->projection->values, 'The PostgreSQL hit is an owner-scoped typed projection');

            return ['hits' => hits($page), 'count' => $page->count];
        });
        $proof->report($database, [], [], $result);

        break;

    case 'tamper':
        $denied = false;

        try {
            Owner::within(static fn (DataScope $scope): SearchPage => rightsQuery($scope, ['fields' => ['title']]));
        } catch (SearchFeatureUnavailable) {
            $denied = true;
        }
        $proof->check(count($trace->backend) === 1 && $trace->backend[0]['records'] !== [], 'The negative control started from a real Meilisearch candidate');
        $proof->same([], $trace->loads, 'Malformed UID, ref or document identity causes zero owner source loads');
        $proof->same(true, $denied, 'The public typed denial yields no hit, projection or continuation');
        $proof->report($database, [], ['backend' => $trace->backend], ['loads' => $trace->loads, 'denied' => 'search-feature-unavailable']);

        break;

    case 'race-reader':
        $result = Owner::within(static function (DataScope $scope) use ($proof): array {
            try {
                $result = rightsQuery($scope);
                $json = json_encode($result, JSON_THROW_ON_ERROR);
                $proof->check(! str_contains($json, 'private-a'), 'A bounded requery excludes the revoked field');

                return ['state' => 'revalidated', 'hits' => hits($result)];
            } catch (SearchWitnessChanged) {
                $proof->check(true, 'A changed native rights witness refuses to serialize the hydrated projection');

                return ['state' => 'witness-changed'];
            }
        });
        $proof->report($database, [], Scenario::barrierRead('rights-projected'), $result);

        break;

    case 'race-revoke':
        $projected = Scenario::barrierRead('rights-projected');
        $proof->same('private-a', $projected['values']['secret'], 'The independent writer sees the previously authorized value at the named projection barrier');
        Owner::within(static function (): void {
            resolve(RoleAdministration::class)->setScope(role(Scenario::W1), SearchScenarioRecord::ENTITY, 'field.secret.read', Scope::None);
        });
        $proof->report($database, [], ['projection' => $projected], ['role' => role(Scenario::W1), 'revoked' => 'field.secret.read']);

        break;

    default:
        throw new RuntimeException('Unknown installed search-rights action.');
}
