<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Contracts;

use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\TasksWorkflow\Data\MaterializationReceipt;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;

interface TaskRecurrenceCommand
{
    public function materialize(DataScope $scope, SeriesReference $series, MaterializationWindow $window, WriteToken $token): MaterializationReceipt;
}
