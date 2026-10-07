<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('keeps migration bytes untouched by Pint and Rector with working negative controls', function (): void {
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/corex-migration-tools-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $migration = '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { $flag = strpos("sample", "sam") !== false; } };';

    try {
        foreach (['packages/core/src', 'tests', 'scripts', 'packages/core/database/migrations/tenant', 'products/demo/database/migrations/budget'] as $dir) {
            $files->makeDirectory($fixture.'/'.$dir, 0755, true);
        }
        $inventory = json_decode($files->get($root.'/scripts/schema-sets.json'), true, flags: JSON_THROW_ON_ERROR);
        $paths = ['products/demo/database/migrations/budget/0001.php'];
        $before = [];
        foreach ($inventory['sets'] as $set) {
            $dir = $set['path'];
            $files->ensureDirectoryExists($fixture.'/'.$dir);
            foreach ($files->allFiles($root.'/'.$dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = $dir.'/'.$file->getRelativePathname();
                $files->ensureDirectoryExists(dirname($fixture.'/'.$path));
                $files->copy($file->getPathname(), $fixture.'/'.$path);
                $before[$path] = hash_file('sha256', $fixture.'/'.$path);
            }
            $paths[] = $dir.'/tooling_negative_control.php';
        }
        foreach ($paths as $path) {
            $files->put($fixture.'/'.$path, $migration);
            $before[$path] = hash_file('sha256', $fixture.'/'.$path);
        }
        $files->copy($root.'/pint.json', $fixture.'/pint.json');
        $files->ensureDirectoryExists($fixture.'/vendor/composer');
        $files->copy($root.'/vendor/composer/installed.json', $fixture.'/vendor/composer/installed.json');
        $files->copy($root.'/composer.json', $fixture.'/composer.json');
        $files->copy($root.'/composer.lock', $fixture.'/composer.lock');
        $files->copyDirectory($root.'/scripts/quality/rector/Laravel/config', $fixture.'/scripts/quality/rector/Laravel/config');
        $cliGuard = 'packages/core/src/Modules/Laravel/ModulesBootRegistrar.php';
        $files->ensureDirectoryExists(dirname($fixture.'/'.$cliGuard));
        $files->copy($root.'/'.$cliGuard, $fixture.'/'.$cliGuard);
        $rector = $files->get($root.'/rector.php');
        $files->put($fixture.'/rector.php', $rector);
        $run = static function (array $command) use ($fixture): Process {
            $process = new Process($command, $fixture, ['PAO_DISABLE' => '1'], timeout: 120);
            $process->run();

            return $process;
        };
        $pint = [PHP_BINARY, $root.'/vendor/bin/pint', '--config='.$fixture.'/pint.json'];
        $rectorCommand = [PHP_BINARY, $root.'/vendor/bin/rector', 'process', 'packages', 'products', '--config='.$fixture.'/rector.php', '--no-progress-bar', '--debug'];
        foreach ([$pint, $rectorCommand] as $command) {
            $result = $run($command);
            expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput());
            foreach ($before as $path => $hash) {
                expect(hash_file('sha256', $fixture.'/'.$path))->toBe($hash, $path);
            }
        }
        $config = json_decode($files->get($fixture.'/pint.json'), true, flags: JSON_THROW_ON_ERROR);
        $config['notPath'] = [];
        $files->put($fixture.'/pint.json', json_encode($config, JSON_THROW_ON_ERROR));
        expect($run($pint)->getExitCode())->toBe(0);
        foreach ($paths as $path) {
            expect($files->get($fixture.'/'.$path))->not->toBe($migration);
            $files->put($fixture.'/'.$path, $migration);
        }
        $withoutSkip = preg_replace('/    ->withSkip\(\[.*?\]\)\n/s', '', $rector);
        $files->put($fixture.'/rector.php', $withoutSkip);
        $result = $run($rectorCommand);
        expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput());
        foreach ($paths as $path) {
            expect($files->get($fixture.'/'.$path))->not->toBe($migration);
        }
    } finally {
        $files->deleteDirectory($fixture);
    }
});
