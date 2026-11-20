<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Data\CurrentSearchRecord;
use CoreX\Search\Data\SearchQuery;
use CoreX\Search\Testing\SearchFixtures;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

final readonly class ScenarioJob implements ShouldQueue
{
    public int $tries;

    public function __construct(public string $reference, public string $operation)
    {
        $this->tries = 1;
    }

    public function handle(ScenarioJobLedger $ledger): void
    {
        if ($this->operation === 'sweep') {
            if (Artisan::call('corex:scheduler:sweep', ['--limit' => 1]) !== 0) {
                throw new RuntimeException('The resumed scheduler sweep failed.');
            }

            return;
        }

        $context = app(TenantContextResolver::class)->current();
        $scope = app(DataScopeFactory::class)->forCurrentPrincipal($context);
        $provider = app(SearchProvider::class);
        $query = new SearchQuery(ScenarioSearchSchema::ENTITY, ScenarioSearchSchema::hash(), 'equipment');

        if ($this->operation === 'nested-b') {
            $ledger->retainedB = $provider;

            try {
                ($ledger->retained ?? throw new RuntimeException('Missing retained A driver.'))->query($scope, $query);
            } catch (ScopeContextMismatch $error) {
                if (! str_contains($error->getMessage(), 'connection')) {
                    throw $error;
                }
                $ledger->failures[] = 'retained-driver-denied';
            }

            if (! in_array('retained-driver-denied', $ledger->failures, true)) {
                throw new RuntimeException('A retained driver executed in B.');
            }
        }

        $hits = $provider->query($scope, $query);
        $schema = app(SearchSchema::class)->describe($scope, ScenarioSearchSchema::ENTITY);
        $authorizer = app(DataScopeAuthorizer::class);
        $hydrator = SearchFixtures::policyHydrator(static function ($scope, $ref, $schema): ?CurrentSearchRecord {
            $row = DB::table('scenario_records')->find($ref->id);

            return $row === null ? null : new CurrentSearchRecord($ref, $schema->schemaHash, hash('sha256', $row->marker), (array) $row);
        }, $authorizer);
        $page = $hydrator->authorize($scope, $hits, $schema);
        $hit = $hits->candidates[0] ?? throw new RuntimeException('PostgreSQL Search returned no candidates.');
        $hidden = $scope->accountId === Scenario::ACCOUNT_B ? 'tenant_a_label' : 'tenant_b_label';
        $ledger->queries[] = [
            'reference' => $this->reference,
            'account' => $context->id(),
            'workspace' => $context->workspace?->id,
            'principal' => $scope->principalId,
            'database' => Scenario::describe(DB::connection()),
            'record' => $hit->record->id,
            'records' => $page->records,
            'hidden_allowed' => $authorizer->authorize($scope, $hit->record, new FieldSelection([$hidden]), FieldUse::Read)->allowed,
            'marker' => DB::table('scenario_records')->where('id', $hit->record->id)->value('marker'),
        ];

        if ($this->operation === 'outer') {
            $this->nested($ledger, $provider);
        }

        if ($this->operation === 'nested-b' || $this->operation === 'failing-b') {
            throw new ScenarioJobFailure('Intentional exception after resolving the B Search driver.');
        }
    }

    private function nested(ScenarioJobLedger $ledger, SearchProvider $provider): void
    {
        $ledger->retained = $provider;
        $guard = auth()->guard();
        $outer = $guard->user();
        $manager = app(TenancyManager::class);

        try {
            $manager->runFor(Scenario::account(Scenario::ACCOUNT_B), Scenario::workspace(Scenario::W2), static function () use ($ledger): void {
                $contexts = app(TenantContextResolver::class);
                app(ScenarioPrincipalAuthority::class)->authenticate($contexts->current(), Scenario::USER_B);

                try {
                    Queue::connection('sync')->push(new self('nested-b', 'nested-b'));
                } finally {
                    $ledger->failures[] = [
                        'returned_from_job' => $contexts->current()->id(),
                        'session' => Scenario::describe(DB::connection()),
                    ];
                }
            });

            throw new RuntimeException('The nested queue exception was swallowed.');
        } catch (ScenarioJobFailure) {
            $ledger->failures[] = 'nested-error-observed';
        } finally {
            $guard->forgetUser();

            if ($outer !== null) {
                $guard->setUser($outer);
            }
            $context = app(TenantContextResolver::class)->current();

            if ($ledger->retainedB !== null) {
                try {
                    $scope = app(DataScopeFactory::class)->forCurrentPrincipal($context);
                    $ledger->retainedB->query($scope, new SearchQuery(ScenarioSearchSchema::ENTITY, ScenarioSearchSchema::hash(), 'equipment'));

                    throw new RuntimeException('A retained B driver executed after restoring A.');
                } catch (ScopeContextMismatch $error) {
                    if (! str_contains($error->getMessage(), 'connection')) {
                        throw $error;
                    }
                    $ledger->failures[] = 'discarded-b-driver-denied';
                }
            }
            $ledger->failures[] = [
                'outer_account' => $context->id(),
                'outer_workspace' => $context->workspace?->id,
                'outer_principal' => $guard->user()?->getAuthIdentifier(),
                'outer_db' => Scenario::describe(DB::connection()),
            ];
        }
    }
}
