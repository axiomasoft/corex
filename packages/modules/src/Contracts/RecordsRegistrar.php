<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

/**
 * Ownership registry for everything a module created in the current
 * tenant's database (B-10 §3.3, `mod_records` — mirrors Odoo `ir_model_data`).
 * Purge walks this registry in reverse `created_at` order — the foundation
 * of a predictable, FK-safe teardown (B-10 §5.4).
 */
interface RecordsRegistrar
{
    /** @param  array<string, mixed>  $meta */
    public function claim(string $module, string $recordType, string $reference, array $meta = []): void;

    public function release(string $module, string $recordType, string $reference): void;
}
