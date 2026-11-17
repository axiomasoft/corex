<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Contracts;

use CoreX\Attributes\Api;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\RecurringTasks\Data\MaterializationReceipt;
use CoreX\RecurringTasks\Data\MaterializationWindow;
use CoreX\RecurringTasks\Data\SeriesReference;

#[Api(since: '1.0')]
interface TaskRecurrenceCommand
{
    public function materialize(DataScope $scope, SeriesReference $series, MaterializationWindow $window, WriteToken $token): MaterializationReceipt;
}
