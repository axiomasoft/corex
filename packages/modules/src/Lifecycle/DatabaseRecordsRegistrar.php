<?php

declare(strict_types=1);

namespace Flex\Modules\Lifecycle;

use Flex\Modules\Contracts\RecordsRegistrar;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed {@see RecordsRegistrar} over `mod_records`. `$connection` is
 * null (the app's default — correct in production, D12) unless a caller
 * pins an explicit name (tests — see DatabaseModuleLifecycle's constructor
 * docblock for why).
 */
final class DatabaseRecordsRegistrar implements RecordsRegistrar
{
    public function __construct(private readonly ?string $connection = null) {}

    /** @param  array<string, mixed>  $meta */
    public function claim(string $module, string $recordType, string $reference, array $meta = []): void
    {
        // Microsecond-precision, pre-formatted (not a bare Carbon instance):
        // Laravel's query-builder date format is second-precision, and purge
        // orders by created_at DESC — two claims in the same wall-clock
        // second must still stay orderable (B-10 §5.4).
        $now = now()->format('Y-m-d H:i:s.u');
        $identity = ['module_name' => $module, 'record_type' => $recordType, 'reference' => $reference];
        $table = DB::connection($this->connection)->table('mod_records');

        // Re-claiming is idempotent (EnableHook retries — B-10 §7.4 п.6): update
        // meta in place, keep the original created_at so purge order stays stable.
        if ($table->where($identity)->exists()) {
            $table->where($identity)->update([
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);

            return;
        }

        $table->insert([
            ...$identity,
            'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function release(string $module, string $recordType, string $reference): void
    {
        DB::connection($this->connection)->table('mod_records')
            ->where('module_name', $module)
            ->where('record_type', $recordType)
            ->where('reference', $reference)
            ->delete();
    }
}
