<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use App\Scenario\ScenarioSearchSchema;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\ModuleRef;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** The seed manifest records physical identities and owners when provisioning this closed deployment. */
final class ReferenceOrigins implements ReferenceOwners
{
    public function resolve(DataScope $scope, string $producer, string $kind, array $witness): array
    {
        app(CurrentWorkScope::class)->assert($scope);

        if ($producer === 'axiomasoft/corex-tenancy' && isset($witness['record'])) {
            $source = Scenario::connection('control')->table('scenario_seed_origins')->where([
                'physical' => str_starts_with($kind, 'root_') ? 'central' : $scope->bindingId,
                'owner_table' => $kind, 'record' => $witness['record'],
            ])->first();

            if ($source !== null && $source->owner === $producer) {
                return [new ModuleRef($producer)];
            }
        }

        if ($producer === 'axiomasoft/corex-custom-fields' && ($witness['entity'] ?? null) === Inventory::ENTITY) {
            return [new ModuleRef($producer), new ModuleRef(Inventory::MODULE)];
        }

        if ($producer === 'axiomasoft/corex-search' && $scope->accountId === Scenario::ACCOUNT_B && $scope->workspaceId === null) {
            $entity = $witness['entity'] ?? null;

            if ($kind === 'provider-send') {
                $entry = DB::table('corex_search_submissions as submission')
                    ->join('corex_search_lanes as lane', 'lane.lane_key', '=', 'submission.lane_key')
                    ->where(['submission.submission_id' => $witness['owner_ref'], 'lane.account_id' => $scope->accountId,
                        'lane.binding_id' => $scope->bindingId, 'lane.restore_epoch' => $scope->restoreEpoch,
                        'lane.workspace_scope' => 'account', 'lane.entity' => ScenarioSearchSchema::ENTITY])
                    ->select(['submission.uid', 'lane.backend', 'lane.cluster', 'lane.entity'])->sole();

                if ($entry->cluster !== getenv('COREX_SCENARIO_MEILI_NAMESPACE')
                    || $witness['provider'] !== hash('sha256', $entry->backend.'|'.$entry->cluster.'|'.$entry->uid)) {
                    throw new RuntimeException('Unknown provider submission in the indexed corpus.');
                }
                $entity = $entry->entity;
            }

            if ($entity === ScenarioSearchSchema::ENTITY && in_array($kind, ['search-lane', 'generation', 'provider-send'], true)) {
                return [new ModuleRef($producer), new ModuleRef(HostModule::PACKAGE)];
            }
        }

        throw new RuntimeException('Unverified original contribution '.$producer.'/'.$kind);
    }
}
