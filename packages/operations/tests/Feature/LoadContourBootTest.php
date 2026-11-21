<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Cost\Data\CostOptions;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Tests\TestCase;
use Illuminate\Foundation\Application;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LoadContourBootTest extends TestCase
{
    public function test_public_surface_keeps_transfer_optional_on_laravel_13(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        $require = $composer['require'];

        self::assertArrayNotHasKey('public-api', $composer['extra']['corex']);
        $surface = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/contracts/php-api.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey(OperationsServiceProvider::class, $surface['symbols']);
        self::assertSame('spi', $surface['symbols'][OperationsServiceProvider::class]['layer']);
        self::assertSame('call', $surface['symbols'][OperationsServiceProvider::class]['spi_kind']);
        self::assertSame(['ext-filter', 'ext-mbstring', 'php', 'axiomasoft/corex', 'illuminate/auth', 'illuminate/console', 'illuminate/contracts', 'illuminate/database', 'illuminate/http', 'illuminate/support', 'illuminate/validation', 'psr/clock', 'symfony/http-kernel', 'axiomasoft/packman', 'axiomasoft/packman-modules', 'axiomasoft/packman-schema'], array_keys($require));
        self::assertSame('^13.0', $require['illuminate/http']);
        self::assertSame('^13.0', $require['illuminate/support']);
        self::assertSame('^13.0', $require['illuminate/validation']);
        self::assertSame(['opis/json-schema', 'ext-curl'], array_keys($composer['suggest']));
        self::assertSame(['axiomasoft/corex-webhooks'], array_values(array_filter(
            array_keys($composer['require-dev']),
            static fn (string $dependency): bool => str_starts_with($dependency, 'axiomasoft/corex-'),
        )));
        self::assertMatchesRegularExpression('/\A13\./', Application::VERSION);
        self::assertSame([], $this->foreignInternalImports());

        $gate = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/scripts/package-graph-gate.json'), true);
        $edges = array_values(array_filter(
            $gate['allowlist'],
            static fn (array $entry): bool => $entry['from'] === 'axiomasoft/corex-operations',
        ));

        self::assertSame([], $edges);
    }

    public function test_boot_keeps_cost_and_bulk_transfer_disabled(): void
    {
        self::assertFalse($this->app->make(CostOptions::class)->enabled);
        self::assertFalse($this->app->bound(BulkExecutor::class));
    }

    /** @return list<string> */
    private function foreignInternalImports(): array
    {
        $imports = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('/^use CoreX\\\\(?!Operations\\\\)[^;]*\\\\Internal\\\\/m', $contents) === 1) {
                $imports[] = $file->getPathname();
            }
        }

        sort($imports);

        return $imports;
    }
}
