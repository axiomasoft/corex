<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\LiveCandidateDriver;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchQuery;
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
        $provider = app(CandidateDriver::class);

        if (! $provider instanceof LiveCandidateDriver) {
            throw new RuntimeException('The queue scenario expected the live installed PostgreSQL driver.');
        }
        $entity = ScenarioSearchSchema::entity();
        $query = new SearchQuery($entity, 'equipment', ['marker'], fields: ['tenant_a_label', 'tenant_b_label']);

        if ($this->operation === 'nested-b') {
            $ledger->retainedB = $provider;

            try {
                ($ledger->retained ?? throw new RuntimeException('Missing retained A driver.'))->target($scope, $entity, hash('sha256', 'queue-retained-target'));
            } catch (ScopeContextMismatch|ScopeStale $error) {
                if (! str_contains($error->getMessage(), 'connection')) {
                    throw $error;
                }
                $ledger->failures[] = 'retained-driver-denied';
            }

            if (! in_array('retained-driver-denied', $ledger->failures, true)) {
                throw new RuntimeException('A retained driver executed in B.');
            }
        }

        $page = app(Search::class)->query($scope, $query);
        $authorizer = app(DataScopeAuthorizer::class);
        $hit = $page->hits[0] ?? throw new RuntimeException('PostgreSQL Search returned no visible hits.');
        $hidden = $scope->accountId === Scenario::ACCOUNT_B ? 'tenant_a_label' : 'tenant_b_label';
        $ledger->queries[] = [
            'reference' => $this->reference,
            'account' => $context->id(),
            'workspace' => $context->workspace?->id,
            'principal' => $scope->principalId,
            'database' => Scenario::describe(DB::connection()),
            'record' => $hit->record->id,
            'records' => array_map(static fn ($item): array => $item->projection->values, $page->hits),
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

    private function nested(ScenarioJobLedger $ledger, LiveCandidateDriver $provider): void
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
                    $ledger->retainedB->target($scope, ScenarioSearchSchema::entity(), hash('sha256', 'queue-retained-target'));

                    throw new RuntimeException('A retained B driver executed after restoring A.');
                } catch (ScopeContextMismatch|ScopeStale $error) {
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
