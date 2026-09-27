<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ForeignHelperWriter
{
    public function write(): void
    {
        $this->settings()->insert(['key' => 'probe']);
    }

    private function settings(): Builder
    {
        return DB::table('sys_settings');
    }
}
