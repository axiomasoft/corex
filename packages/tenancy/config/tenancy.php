<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Central connection (B-11 §2.1 / D14)
    |--------------------------------------------------------------------------
    | root_* migrations and models target this connection — the control-plane
    | database, never a tenant/account database. Override per-host if the
    | central DB uses a different Laravel connection name.
    */
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | stancl/tenancy v4 pin (D8/D75/D76 — GO on 2026-07-18 PoC gate)
    |--------------------------------------------------------------------------
    | stancl/tenancy master carries no tagged release; the exact sha is the
    | supply-chain anchor (D25 fork-mirror is a deferred follow-up, OQ-4).
    */
    'stancl' => [
        'package' => 'stancl/tenancy',
        'pin' => 'dev-master#76e5f9655924271c6c9da54e2d4ccb82c6f71ea0 as 4.0.0',
        'jobpipeline_pin' => '2.0.0-rc7',
    ],

];
