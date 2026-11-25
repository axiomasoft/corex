<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use App\Scenario\ScenarioSearchSchema;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use Illuminate\Support\Facades\DB;

/** Read authority for the explicitly scheduled index job on the physical B corpus. */
final class SearchCorpusGrants implements DataScopeAuthorizer, FieldUsePolicy
{
    public const string SERVICE = 'corex.scheduler-sweep';

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        resolve(CurrentWorkScope::class)->assert($scope, DB::connection());
        $fields = $request->fields->fields ?: ['record'];

        if ($entity->value !== ScenarioSearchSchema::ENTITY || $scope->principalKind !== 'service'
            || $scope->principalId !== self::SERVICE || $scope->accountId !== Scenario::ACCOUNT_B
            || $scope->workspaceId !== null || $request->use !== FieldUse::Read) {
            return FieldUsePlan::denied();
        }
        $grants = DB::table('scenario_index_fields')->where('scope', hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)))
            ->where('entity', $entity->value)->whereIn('field', $fields)->orderBy('field')->pluck('field')->all();

        return count($grants) === count($fields)
            ? FieldUsePlan::unconditional(hash('sha256', json_encode([$scope, $entity, $grants], JSON_THROW_ON_ERROR)))
            : FieldUsePlan::denied();
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $plan = $this->plan($scope, new EntityHandle($record->entity), new FieldUseRequest($fields, $use));
        $allowed = $plan->kind === FieldUsePlanKind::Unconditional && DB::table('scenario_records')->where('id', $record->id)->exists();

        return new DataScopeAccessDecision($allowed, $allowed ? 'scenario.granted' : 'scenario.denied');
    }
}
