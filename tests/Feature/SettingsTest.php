<?php

declare(strict_types=1);

use CoreX\Contracts\SettingsRepository;
use CoreX\Enums\SettingScope;

beforeEach(function (): void {
    $this->settings = app(SettingsRepository::class);
});

it('stores and reads system settings', function (): void {
    $this->settings->set('mail.driver', 'smtp');

    expect($this->settings->get('mail.driver'))->toBe('smtp')
        ->and($this->settings->get('missing', 'fallback'))->toBe('fallback');
});

it('separates values by scope and scope id', function (): void {
    $this->settings->set('theme', 'dark', SettingScope::User, 'user-1');

    expect($this->settings->get('theme', 'light', SettingScope::User, 'user-1'))->toBe('dark')
        ->and($this->settings->get('theme', 'light', SettingScope::User, 'user-2'))->toBe('light')
        ->and($this->settings->get('theme', 'light'))->toBe('light');
});

it('preserves value types and can forget a key', function (): void {
    $this->settings->set('flags', ['a' => true, 'b' => 2]);
    expect($this->settings->get('flags'))->toBe(['a' => true, 'b' => 2]);

    $this->settings->forget('flags');
    expect($this->settings->get('flags', 'gone'))->toBe('gone');
});
