<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Data\CompiledRegistry;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;

/** Selected P3–P6 archive cohort contributions for CONTRIB qualification. */
final class ArchiveToolModule implements ModuleRegistry
{
    public const COMPOSER_NAME = 'example/archive-tools';

    public bool $enabled = true;

    public int $epoch = 1;

    /** @var list<string> */
    public array $visitedAccounts = [];

    public function __construct(public CompiledRegistry $registry) {}

    public static function compile(): CompiledRegistry
    {
        $input = self::inputSchema();
        $output = self::outputSchema();

        return app(RegistryCompiler::class)->compile([
            ModuleManifest::make(self::COMPOSER_NAME)
                ->title('Archive tools')
                ->tablePrefix('arc')
                ->extends([
                    Api::tool('files.read_retained', CountingHandler::class, 'files.read', $input, $output, 'read', 'read-only')
                        ->titled('Read retained file', 'Archive-lab storage read port'),
                    Api::tool('maintenance.export_account', CountingHandler::class, 'maintenance.export', $input, $output, 'write', 'domain-key')
                        ->titled('Export account snapshot', 'Archive-lab maintenance export port'),
                    Api::tool('identity.read_context', CountingHandler::class, 'identity.read', $input, $output, 'read', 'read-only')
                        ->titled('Read identity context', 'Identity read without root writer'),
                    Api::tool('accounts.list_menu', CountingHandler::class, 'accounts.list', $input, $output, 'read', 'read-only')
                        ->titled('List account menu', 'Archive-lab account list read port'),
                    Api::tool('panels.render_slot', CountingHandler::class, 'panels.read', $input, $output, 'read', 'read-only')
                        ->titled('Render panel slot', 'Approved P4 panel contribution'),
                    Api::tool('workflow.wakeup_local', CountingHandler::class, 'workflow.wakeup', $input, $output, 'read', 'read-only')
                        ->titled('Local workflow wakeup', 'Approved P5 wakeup contribution'),
                    Api::tool('search.query', CountingHandler::class, 'search.read', $input, $output, 'read', 'read-only')
                        ->titled('Search query', 'Approved P6 search contribution'),
                    Api::entity('archive.file'),
                ]),
        ], installedPackages: []);
    }

    /** @return array<string, mixed> */
    public static function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => ['name' => ['type' => 'string']],
        ];
    }

    /** @return array<string, mixed> */
    public static function outputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'name' => ['type' => 'string'],
                'actor' => ['type' => 'string'],
                'account' => ['type' => 'string'],
                'operation_key' => ['type' => ['string', 'null']],
                'contract_hash' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function objectSchema(): array
    {
        return self::inputSchema();
    }

    public function all(): array
    {
        return [new CompiledModule(
            composerName: self::COMPOSER_NAME,
            title: 'Archive tools',
            tablePrefix: 'arc',
            requires: [],
            editions: [],
            components: [],
        )];
    }

    public function get(string $name): CompiledModule
    {
        return $this->all()[0];
    }

    public function active(TenantContext $ctx): array
    {
        return $this->enabled ? $this->all() : [];
    }

    public function isActive(string $name, TenantContext $ctx): bool
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
