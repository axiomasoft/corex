<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Registry\CompiledModule;
use CoreX\Tenancy\TenantContext;
use LogicException;

final class ModuleFixture implements ModuleRegistry
{
    public bool $enabled = true;

    /** @var list<string> */
    public array $visitedAccounts = [];

    public function __construct(public CompiledRegistry $registry) {}

    public function all(): array
    {
        return [];
    }

    public function get(string $name): CompiledModule
    {
        throw new LogicException('Not used.');
    }

    public function active(TenantContext $ctx): array
    {
        return [];
    }

    public function isActive(string $name, TenantContext $ctx): bool
    {
        $this->visitedAccounts[] = $ctx->account->id;

        return $this->enabled && $name === 'example/notes';
    }

    public function compiled(): CompiledRegistry
    {
        return $this->registry;
    }
}
