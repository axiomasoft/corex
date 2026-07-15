<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Manifest-declared feature-flag default (B-10 §3.1). `define()` is
 * idempotent — registering the same key again never overwrites an
 * admin-toggled `is_enabled` (mirrors `SettingDefaultsProvider`'s
 * register-once semantics).
 */
final readonly class FeatureFlagDefinition
{
    /** @param  array<string, mixed>|null  $payload */
    public function __construct(
        public string $key,
        public bool $default = false,
        public ?string $module = null,
        public ?array $payload = null,
    ) {}
}
