<?php

declare(strict_types=1);

namespace Flex\Modules\Registry;

use CoreX\Tenancy\TenantContext;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\Contracts\ModuleStateReader;
use Flex\Modules\Exceptions\ModuleNotFound;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * File-backed {@see ModuleRegistry} (B-10 §5.2/§7.1). `all()`/`get()` read
 * the compiled file written by `flex:modules:compile` (opcache after the
 * first `require`, 0 fs scans on later calls); `active()` intersects it with
 * a per-account tagged cache backed by {@see ModuleStateReader} — a cache
 * miss costs exactly one reader call, a warm cache costs none (AC-3/AC-4).
 */
final class CompiledModuleRegistry implements ModuleRegistry
{
    /** @var array<string, CompiledModule>|null */
    private ?array $modules = null;

    public function __construct(
        private readonly string $cachePath,
        private readonly ModuleStateReader $stateReader,
        private readonly CacheRepository $cache,
        private readonly int $cacheTtl = 3600,
    ) {}

    public static function defaultCachePath(): string
    {
        return base_path('bootstrap/cache/flex_modules.php');
    }

    public function all(): array
    {
        return array_values($this->modules());
    }

    public function get(string $name): CompiledModule
    {
        return $this->modules()[$name] ?? throw new ModuleNotFound($name);
    }

    public function active(TenantContext $ctx): array
    {
        $enabled = $this->enabledFor($ctx);

        return array_values(array_filter(
            $this->modules(),
            static fn (CompiledModule $module): bool => array_key_exists($module->composerName, $enabled),
        ));
    }

    public function isActive(string $name, TenantContext $ctx): bool
    {
        return array_key_exists($name, $this->enabledFor($ctx));
    }

    /** @return array<string, array{state: string, edition: string}> */
    private function enabledFor(TenantContext $ctx): array
    {
        $accountId = $ctx->account->id;

        return $this->cache
            ->tags(["account:{$accountId}:modules"])
            ->remember(
                "account:{$accountId}:modules:enabled",
                $this->cacheTtl,
                fn (): array => $this->stateReader->enabledFor($ctx),
            );
    }

    /** @return array<string, CompiledModule> */
    private function modules(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        if (! is_file($this->cachePath)) {
            return $this->modules = [];
        }

        /** @var array{modules?: list<array{composerName: string, title: ?string, tablePrefix: ?string, requires: list<array{type: string, target: string, constraint: string}>, editions: array<string, list<string>>, components: list<class-string>}>} $data */
        $data = require $this->cachePath;

        $modules = [];

        foreach ($data['modules'] ?? [] as $row) {
            $module = CompiledModule::fromArray($row);
            $modules[$module->composerName] = $module;
        }

        return $this->modules = $modules;
    }

    /** @param  list<CompiledModule>  $modules */
    public static function writeAtomic(string $path, array $modules): void
    {
        $payload = ['modules' => array_map(static fn (CompiledModule $module): array => $module->toArray(), $modules)];

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $tmpPath = $path.'.'.bin2hex(random_bytes(8)).'.tmp';

        file_put_contents($tmpPath, "<?php\n\nreturn ".var_export($payload, true).";\n", LOCK_EX);
        rename($tmpPath, $path);
    }
}
