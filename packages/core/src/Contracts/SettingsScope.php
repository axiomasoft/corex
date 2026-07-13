<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Enums\SettingScope;

/**
 * Addresses one cascade level of a setting read/write (B-10 §3.1). A single
 * instance names ONE level (account/workspace/user) — composing the full
 * user→workspace→account chain from one call happens inside the repository
 * (see DatabaseSettingsRepository), not here.
 */
final readonly class SettingsScope
{
    private function __construct(
        public SettingScope $type,
        public ?string $id = null,
    ) {}

    public static function account(): self
    {
        return new self(SettingScope::Account);
    }

    public static function workspace(string $workspaceId): self
    {
        return new self(SettingScope::Workspace, $workspaceId);
    }

    public static function user(string $userId): self
    {
        return new self(SettingScope::User, $userId);
    }
}
