<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SearchScenarioData
{
    public const string SERVICE = 'corex.scenario-search-index';

    public const string FIRST = '40000000-0000-7000-8000-000000000051';

    public const string SECOND = '40000000-0000-7000-8000-000000000052';

    public const string THIRD = '40000000-0000-7000-8000-000000000053';

    public static function install(string $case): Connection
    {
        ScenarioSetup::install($case, false);
        Scenario::connection('control')->getSchemaBuilder()->create('scenario_search_broker', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('wire');
            $table->string('quarantine')->nullable();
        });
        $db = Scenario::connection('a');
        $workspace = Scenario::workspace(Scenario::W2);
        $db->table('wsp_workspaces')->insert(['id' => $workspace->id, 'slug' => $workspace->slug,
            'name' => $workspace->slug, 'type' => $workspace->type, 'path' => $workspace->path]);
        $db->table('users')->insert(['id' => Scenario::USER_B, 'email' => 'search-b@example.test', 'name' => 'Search B']);
        $db->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => Scenario::W2,
            'user_id' => Scenario::USER_B, 'status' => 'active']);
        $schema = $db->getSchemaBuilder();
        $schema->create('scenario_search_records', static function (Blueprint $table): void {
            $table->uuid('workspace_id');
            $table->uuid('id');
            $table->uuid('owner_id');
            $table->string('title');
            $table->string('secret');
            $table->boolean('visible_flag');
            $table->integer('rank');
            $table->string('revision');
            $table->primary(['workspace_id', 'id']);
        });
        $schema->create('scenario_search_service_grants', static function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->uuid('workspace_id');
            $table->string('service');
            $table->primary(['account_id', 'workspace_id', 'service']);
        });
        foreach ([Scenario::W1, Scenario::W2] as $id) {
            $db->table('scenario_search_service_grants')->insert(['account_id' => Scenario::BOXED, 'workspace_id' => $id, 'service' => self::SERVICE]);
        }

        return $db;
    }

    public static function entity(): EntityHandle
    {
        return new EntityHandle(SearchScenarioRecord::ENTITY);
    }

    public static function record(string $id): RecordRef
    {
        return new RecordRef(SearchScenarioRecord::ENTITY, $id);
    }

    public static function put(Connection $db, string $id, string $title, string $secret, int $rank, string $workspace = Scenario::W1, ?string $owner = null): void
    {
        $db->table('scenario_search_records')->updateOrInsert(['workspace_id' => $workspace, 'id' => $id], [
            'owner_id' => $owner ?? ($workspace === Scenario::W1 ? Scenario::USER_A : Scenario::USER_B),
            'title' => $title, 'secret' => $secret, 'visible_flag' => true, 'rank' => $rank, 'revision' => (string) Str::uuid7(),
        ]);
    }

    public static function rows(DataScope $scope, EntityHandle $entity): Builder
    {
        if ($entity->value !== SearchScenarioRecord::ENTITY || $scope->workspaceId === null) {
            throw new RuntimeException('The fixture has no source in that entity or workspace.');
        }
        $db = DB::connection();
        app(CurrentWorkScope::class)->assert($scope, $db);

        return $db->table('scenario_search_records')->where('workspace_id', $scope->workspaceId);
    }

    public static function blockingTickets(): array
    {
        return Scenario::connection('control')->table('corex_scope_tickets')->where('account_id', Scenario::BOXED)
            ->whereIn('state', ['outstanding', 'unknown', 'conflicting'])->orderBy('ticket_id')
            ->get(['ticket_id', 'kind', 'state'])->map(static fn (object $row): array => (array) $row)->all();
    }

    public static function within(Closure $work, string $workspace = Scenario::W1, bool $service = false): mixed
    {
        $actor = $workspace === Scenario::W1 ? Scenario::USER_A : Scenario::USER_B;

        return ScenarioQueue::inScope(Scenario::BOXED, $workspace, $actor, static function () use ($service, $work): mixed {
            $context = app(TenantContextResolver::class)->current();
            $run = static fn () => $work(app(DataScopeFactory::class)->forCurrentPrincipal($context));

            return $service ? app(ScenarioPrincipalAuthority::class)->forService($context, self::SERVICE, $run) : $run();
        });
    }
}
