<?php

declare(strict_types=1);

return [
    /* Storage and DDL drivers are selected by the application, never by request input. */
    'drivers' => ['jsonb' => true, 'eav' => true, 'ddl' => true],
    'storage_driver' => 'jsonb',
    'tables' => [
        'documents' => 'sys_attribute_documents',
        'write_tokens' => 'sys_attribute_write_tokens',
        'eav_values' => 'sys_attribute_eav_values',
        'eav_records' => 'sys_attribute_eav_records',
        'schema_operations' => 'sys_attribute_schema_operations',
    ],
];
