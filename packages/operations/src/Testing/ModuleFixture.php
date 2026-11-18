<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use LogicException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\CompiledRegistry;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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

    public function active(ContextScopeInterface $ctx): array
    {
        return [];
    }

    public function isActive(string $name, ContextScopeInterface $ctx): bool
    {
        $this->visitedAccounts[] = $ctx->id();

        return $this->enabled && $name === 'example/notes';
    }

    public function compiled(): CompiledRegistry
    {
        return $this->registry;
    }
}
