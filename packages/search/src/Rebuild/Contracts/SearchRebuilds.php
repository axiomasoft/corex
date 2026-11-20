<?php

declare(strict_types=1);

namespace CoreX\Search\Rebuild\Contracts;

use CoreX\Context\Data\OperationKey;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Rebuild\Data\GenerationRef;
use CoreX\Search\Rebuild\Data\RebuildInput;
use CoreX\Search\Rebuild\Data\RebuildPlan;
use CoreX\Search\Rebuild\Data\RebuildPlanRef;
use CoreX\Search\Rebuild\Data\RebuildRef;
use CoreX\Search\Rebuild\Data\RebuildStatus;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
interface SearchRebuilds
{
    public function plan(DataScope $scope, RebuildInput $input): RebuildPlan;

    public function start(DataScope $scope, RebuildPlanRef $plan, OperationKey $key): RebuildRef;

    public function inspect(DataScope $scope, RebuildRef $ref): RebuildStatus;

    public function cancel(DataScope $scope, RebuildRef $ref): RebuildStatus;

    public function rollback(DataScope $scope, RebuildRef $ref, GenerationRef $expected): RebuildRef;
}
