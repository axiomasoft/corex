<?php

declare(strict_types=1);

return [
    /* Storage and DDL drivers are selected by the application, never by request input. */
    'drivers' => ['jsonb' => true, 'eav' => true, 'ddl' => true],
    'storage_driver' => 'jsonb',
    'storage_by_entity' => [],
    'sources' => ['static'],
    'definitions' => [],
];
