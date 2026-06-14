<?php

declare(strict_types=1);

namespace CoreX\Settings;

use CoreX\Contracts\SettingsRepository;
use CoreX\Enums\SettingScope;
use CoreX\Models\Setting;

final class DatabaseSettingsRepository implements SettingsRepository
{
    public function get(string $key, mixed $default = null, SettingScope $scope = SettingScope::System, ?string $scopeId = null): mixed
    {
        $setting = Setting::query()
            ->where('scope', $scope->value)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof Setting) {
            return $default;
        }

        return $this->decode($setting->value);
    }

    public function set(string $key, mixed $value, SettingScope $scope = SettingScope::System, ?string $scopeId = null): void
    {
        Setting::query()->updateOrCreate(
            attributes: ['scope' => $scope->value, 'scope_id' => $scopeId, 'key' => $key],
            values: ['value' => $this->encode($value)],
        );
    }

    public function forget(string $key, SettingScope $scope = SettingScope::System, ?string $scopeId = null): void
    {
        Setting::query()
            ->where('scope', $scope->value)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->delete();
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function decode(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
