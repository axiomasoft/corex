<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Models\Process;
use Illuminate\Support\Facades\DB;

final class CreateProcess
{
    public function handle(string $name, string $code, bool $isDefault = false, ?string $createdById = null): Process
    {
        return DB::transaction(fn (): Process => Process::query()->create([
            'name' => $name,
            'code' => $code,
            'is_default' => $isDefault,
            'created_by_id' => $createdById,
        ]));
    }
}
