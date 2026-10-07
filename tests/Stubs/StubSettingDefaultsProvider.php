<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use CoreX\Settings\Contracts\SettingDefaultsProvider;
use Override;

final readonly class StubSettingDefaultsProvider implements SettingDefaultsProvider
{
    /**
     * @param  array<string, mixed>  $defaults  keyed by "namespace.key"
     * @param  list<string>  $sensitive  "namespace.key" entries treated as sensitive
     */
    public function __construct(
        private array $defaults = [],
        private array $sensitive = [],
    ) {}

    #[Override]
    public function defaultFor(string $namespace, string $key): mixed
    {
        return $this->defaults["{$namespace}.{$key}"] ?? null;
    }

    #[Override]
    public function isSensitive(string $namespace, string $key): bool
    {
        return in_array("{$namespace}.{$key}", $this->sensitive, true);
    }
}
