<?php

declare(strict_types=1);

namespace Flex\Modules\Registry;

use Flex\Modules\Compiler\CompiledRegistry;
use Flex\Modules\Dep;
use Flex\Modules\Manifest;

/**
 * One module's compiled-registry entry (B-10 §3.3 `ModuleRegistry::get()`):
 * manifest metadata needed at the hot path, serialized as plain scalars/
 * arrays so it round-trips through `var_export()` (P1.5 atomic write).
 *
 * Compiled extender output (entities, permissions, menu, api, filament,
 * routes, workflow — {@see CompiledRegistry}) is not
 * persisted here; wiring EntityRegistry and friends to a file-backed source
 * is out of this item's scope (see phases/P1.md P1.5 Pending Work).
 */
final readonly class CompiledModule
{
    /**
     * @param  list<array{type: string, target: string, constraint: string}>  $requires
     * @param  array<string, list<string>>  $editions
     * @param  list<class-string>  $components
     */
    public function __construct(
        public string $composerName,
        public ?string $title,
        public ?string $tablePrefix,
        public array $requires,
        public array $editions,
        public array $components,
    ) {}

    public static function fromManifest(Manifest $manifest): self
    {
        return new self(
            composerName: $manifest->composerName,
            title: $manifest->title,
            tablePrefix: $manifest->tablePrefix,
            requires: array_map(
                static fn (Dep $dep): array => ['type' => $dep->type, 'target' => $dep->target, 'constraint' => $dep->constraint],
                $manifest->requires,
            ),
            editions: $manifest->editions,
            components: $manifest->components,
        );
    }

    /**
     * @param  array{composerName: string, title: ?string, tablePrefix: ?string, requires: list<array{type: string, target: string, constraint: string}>, editions: array<string, list<string>>, components: list<class-string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            composerName: $data['composerName'],
            title: $data['title'],
            tablePrefix: $data['tablePrefix'],
            requires: $data['requires'],
            editions: $data['editions'],
            components: $data['components'],
        );
    }

    /**
     * @return array{composerName: string, title: ?string, tablePrefix: ?string, requires: list<array{type: string, target: string, constraint: string}>, editions: array<string, list<string>>, components: list<class-string>}
     */
    public function toArray(): array
    {
        return [
            'composerName' => $this->composerName,
            'title' => $this->title,
            'tablePrefix' => $this->tablePrefix,
            'requires' => $this->requires,
            'editions' => $this->editions,
            'components' => $this->components,
        ];
    }
}
