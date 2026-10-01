<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Contracts;

use CoreX\Attributes\Api;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\TasksWorkflow\Data\MaterializationReceipt;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;

#[Api(since: '1.0')]
interface TaskRecurrenceCommand
{
    public function materialize(DataScope $scope, SeriesReference $series, MaterializationWindow $window, WriteToken $token): MaterializationReceipt;
}
