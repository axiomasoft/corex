<?php

declare(strict_types=1);

use CoreX\AccessAzguard\Internal\AzGuardRoleModel;

return [
    'bind_permission_layer' => env('COREX_ACCESS_AZGUARD_BIND_PERMISSION_LAYER', true),
    'azguard_pins' => [
        'table_names' => ['roles' => 'az_guard_roles'],
        'column_names' => ['morph_type' => 'uuid'],
        'models' => ['role' => AzGuardRoleModel::class],
    ],
];
