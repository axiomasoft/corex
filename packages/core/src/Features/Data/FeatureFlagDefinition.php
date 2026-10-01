<?php

declare(strict_types=1);

namespace CoreX\Features\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;

/**
 * ModuleManifest-declared feature-flag default. `FeatureFlags::define()` is
 * idempotent — registering the same key again never overwrites an
 * admin-toggled `is_enabled` (mirrors `SettingDefaultsProvider`'s
 * register-once semantics).
 */
#[Api(since: '1.0')]
final readonly class FeatureFlagDefinition
{
    /** Immutable JSON value. */
    public function __construct(public string $key, public bool $default = false, public ?string $module = null, public ?JsonDocument $payload = null) {}

    public static function make(string $key, bool $default = false): self
    {
        return new self($key, $default);
    }

    public function module(string $module): self
    {
        return new self(
            key: $this->key,
            default: $this->default,
            module: $module,
            payload: JsonDocuments::nullable($this->payload),
        );
    }

    /** Immutable JSON value. */
    public function payload(JsonDocument $payload): self
    {
        return new self(
            key: $this->key,
            default: $this->default,
            module: $this->module,
            payload: JsonDocuments::nullable($payload),
        );
    }
}
