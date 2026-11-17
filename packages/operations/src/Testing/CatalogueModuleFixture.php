<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\CompiledRegistry;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CatalogueModuleFixture implements ModuleRegistry
{
    public bool $enabled = true;

    public int $epoch = 1;

    /** @var list<string> */
    public array $visitedAccounts = [];

    /** @var list<int> */
    public array $epochReads = [];

    public function __construct(public CompiledRegistry $registry) {}

    public function all(): array
    {
        return [new CompiledModule(
            composerName: 'example/notes',
            title: 'Notes',
            tablePrefix: 'nts',
            requires: [],
            editions: [],
            components: [],
        )];
    }

    public function get(string $name): CompiledModule
    {
        return $this->all()[0];
    }

    public function active(ContextScopeInterface $ctx): array
    {
        return $this->enabled ? $this->all() : [];
    }

    public function isActive(string $name, ContextScopeInterface $ctx): bool
    {
        $this->visitedAccounts[] = $ctx->id();
        $this->epochReads[] = $this->epoch;

        return $this->enabled && $name === 'example/notes';
    }

    public function compiled(): CompiledRegistry
    {
        return $this->registry;
    }

    public function bumpEpoch(): void
    {
        $this->epoch++;
    }
}
