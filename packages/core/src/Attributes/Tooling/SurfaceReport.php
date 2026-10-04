<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tooling;

use RuntimeException;

/** @internal One source-derived result shared by CLI, documentation and graph consumers. */
final readonly class SurfaceReport
{
    /**
     * @param  array<string, array{schema: string, package: string, symbols: array<string, array<string, mixed>>}>  $manifests
     * @param  array<string, string>  $owners
     * @param  list<SurfaceDiagnostic>  $diagnostics
     * @param  array<string, string>  $packagePaths
     */
    public function __construct(
        public array $manifests,
        public array $owners,
        public array $diagnostics,
        public array $packagePaths,
    ) {}

    public function json(): string
    {
        return self::encode(['schema' => 'php-api-report/1', 'owners' => $this->owners, 'manifests' => $this->manifests,
            'diagnostics' => array_map(static fn (SurfaceDiagnostic $d): array => $d->toArray(), $this->diagnostics)]);
    }

    /** @param array<string, mixed> $value */
    public static function encode(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n";
    }

    public function documentation(): string
    {
        $text = "# PHP API surface\n\nGenerated from source by `scripts/php-api.php --write`; schema `php-api/1`.\n";
        $text .= "\nAttributes own membership. Members belong to this package; direct extends/implements/uses identify external sources. Full member signatures are in each package's `resources/contracts/php-api.json`.\n";
        foreach ($this->manifests as $package => $manifest) {
            $path = $this->packagePaths[$package];
            $text .= "\n## {$package}\n\n[Member manifest](../../{$path}/resources/contracts/php-api.json)\n\n";
            $text .= "| Symbol | Layer | Since |\n|:--|:--|:--|\n";
            foreach ($manifest['symbols'] as $symbol => $record) {
                $layer = $record['layer'] ?? null;
                $since = $record['since'] ?? null;

                if (! is_string($layer) || ! is_string($since)) {
                    throw new RuntimeException('Invalid source surface record: '.$symbol);
                }
                $text .= "| `{$symbol}` | {$layer} | {$since} |\n";
            }
        }

        return $text;
    }
}
