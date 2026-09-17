<?php

declare(strict_types=1);

return [
    'wakeup' => [
        'enabled' => false,
        'handler_kind' => 'task-recurrence.materialize',
    ],
    'completion_capture' => [
        'enabled' => false,
    ],
];
