<?php

declare(strict_types=1);

return [
    'wakeup' => [
        'enabled' => false,
        'handler_kind' => 'task-recurrence.materialize',
        'handler_version' => 'task-materialization/1',
        'handler' => 'CoreX\\RecurringTasks\\Internal\\MaterializationWakeupHandler',
    ],
];
