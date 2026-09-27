<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

DB::table('sys_settings')->insert(['key' => 'probe']);
