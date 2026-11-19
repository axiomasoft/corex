<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\CompiledRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

/** Selected P7 laboratory cohort contributions; disjoint from archive cohort. */
final class LabToolModule implements ModuleRegistry
{
    public const string COMPOSER_NAME = 'example/lab-tools';

    public bool $enabled = true;

    public int $epoch = 1;

    /** @var list<string> */
    public array $visitedAccounts = [];

    public function __construct(public CompiledRegistry $registry) {}

    public static function compile(): CompiledRegistry
    {
        $input = ArchiveToolModule::inputSchema();
        $output = ArchiveToolModule::outputSchema();

        return resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make(self::COMPOSER_NAME)
                ->title('Lab tools')
                ->tablePrefix('lab')
                ->extends([
                    Api::tool('locale.format', CountingHandler::class, 'locale.read', $input, $output, 'read', 'read-only')
                        ->titled('Format locale', 'Approved P7 locale contribution'),
                    Api::tool('fx.convert', CountingHandler::class, 'fx.read', $input, $output, 'read', 'read-only')
                        ->titled('Convert currency', 'Approved P7 FX contribution'),
                    Api::tool('geo.resolve', CountingHandler::class, 'geo.read', $input, $output, 'read', 'read-only')
                        ->titled('Resolve geography', 'Approved P7 geo contribution'),
                    Api::tool('tasks.complete', CountingHandler::class, 'tasks.write', $input, $output, 'write', 'domain-key')
                        ->titled('Complete task', 'Approved P7 task mutation contribution'),
                    Api::entity('lab.task'),
                ]),
        ], installedPackages: []);
    }

    public function all(): array
    {
        return [new CompiledModule(
            composerName: self::COMPOSER_NAME,
            title: 'Lab tools',
            tablePrefix: 'lab',
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
        $this->visitedAccounts[] = $ctx->account->id;

        return $this->enabled && $name === self::COMPOSER_NAME;
    }

    public function compiled(): CompiledRegistry
    {
        return $this->registry;
    }

    public function flush(): void
    {
        //
    }

    public function bumpEpoch(): void
    {
        $this->epoch++;
    }
}
