<?php

declare(strict_types=1);

use Packman\DevTools\Data\Gates\GateContext;
use Packman\DevTools\Data\Gates\MonorepoConfig;
use Packman\DevTools\Data\Gates\PackageManifest;
use Packman\DevTools\Gates\Boundaries\TableWriteOwnershipGate;
use Packman\DevTools\Gates\Runner\OwnersCatalogue;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use PHPUnit\Framework\TestCase;

final class PackmanGovernanceTest extends TestCase
{
    public function test_every_package_and_product_has_consistent_packman_metadata(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(file_get_contents($root.'/packman.json'), true, flags: JSON_THROW_ON_ERROR);
        $expected = array_merge(glob($root.'/packages/*/composer.json'), glob($root.'/products/*/composer.json'));
        $actual = [];
        foreach ($manifest['packages'] as $package) {
            $path = $root.'/'.$package['path'].'/composer.json';
            $actual[] = $path;
            $composer = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($package['name'], $composer['name']);
            self::assertSame($package['type'], $composer['extra']['packman']['package-type']);
            self::assertSame($package['profile'], $composer['extra']['packman']['profile']);
            self::assertSame($package['owns'], $composer['extra']['packman']['owns']);
        }
        sort($actual);
        sort($expected);
        self::assertNotEmpty($expected);
        self::assertSame($expected, $actual);
    }

    public function test_system_table_owners_match_the_architecture_catalogue(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(file_get_contents($root.'/packman.json'), true, flags: JSON_THROW_ON_ERROR);
        $configured = $manifest['gates']['table_owners'];
        // The catalogue lists these upstream-owned tables separately from the per-table rows.
        $expected = [
            'mod_modules' => 'axiomasoft/packman-modules',
            'mod_records' => 'axiomasoft/packman-modules',
            'mod_lifecycle_log' => 'axiomasoft/packman-modules',
            'sys_saved_view_scopes' => 'axiomasoft/packman-panels',
            'sys_saved_views' => 'axiomasoft/packman-panels',
        ];
        foreach (file($root.'/docs/architecture/table-ownership.md') as $line) {
            if (preg_match('/^\| ([a-z0-9_]+) \| ([a-z0-9-]+\/[a-z0-9-]+) \|/', $line, $match) === 1
                && (str_starts_with($match[1], 'sys_') || str_starts_with($match[1], 'mod_') || array_key_exists($match[1], $configured))) {
                $expected[$match[1]] = $match[2];
            }
        }
        ksort($expected, SORT_STRING);
        self::assertSame($expected, $configured);
    }

    public function test_native_table_write_gate_admits_only_the_declared_owner(): void
    {
        $root = dirname(__DIR__, 2).'/build/packman-owner-'.bin2hex(random_bytes(8));
        mkdir($root.'/packages/probe/src', 0777, true);
        $file = $root.'/packages/probe/src/Write.php';

        try {
            $package = new PackageManifest('fixture/owner', 'packages/probe', 'Fixture\\', 'laravel-package', 'L2', 'capability');
            $config = new MonorepoConfig('fixture/repo', 'modular-platform', '^8.4', 'max', 80, [$package], [
                'table_owners' => ['sys_owned' => 'fixture/owner', 'sys_foreign' => 'fixture/other'],
            ], []);
            $context = new GateContext($root, $config, new OwnersCatalogue($config));
            foreach (['sys_owned' => true, 'sys_foreign' => false, 'sys_unknown' => false, 'mod_records' => false, 'migrations' => false] as $table => $allowed) {
                file_put_contents($file, '<?php $db->table(\''.$table.'\')->insert([\'id\' => 1]);');
                $result = (new TableWriteOwnershipGate)->run($context);
                self::assertSame($allowed, $result->passed, $table);
                self::assertCount($allowed ? 0 : 1, $result->violations);
            }
        } finally {
            unlink($file);
            rmdir(dirname($file));
            rmdir($root.'/packages/probe');
            rmdir($root.'/packages');
            rmdir($root);
        }
    }

    public function test_lifecycle_modules_compose_public_schema_facets(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(file_get_contents($root.'/packman.json'), true, flags: JSON_THROW_ON_ERROR);
        $count = 0;
        foreach ($manifest['packages'] as $package) {
            $composer = json_decode(file_get_contents($root.'/'.$package['path'].'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
            $class = $composer['extra']['packman']['module'] ?? null;

            if ($class === null) {
                self::assertSame('library', $composer['type']);

                continue;
            }
            self::assertSame('packman-module', $composer['type']);
            $module = new $class;
            self::assertInstanceOf(ModuleDefinition::class, $module);
            self::assertInstanceOf(WithSchema::class, $module);
            self::assertSame($module->manifest()->tablePrefix, $module->tablePrefix());
            self::assertNotEmpty($module->schemaSetProviders());
            foreach ($module->schemaSetProviders() as $provider) {
                self::assertTrue(is_subclass_of($provider, SchemaSetProvider::class));
            }
            $count++;
        }
        self::assertSame(8, $count);
    }
}
