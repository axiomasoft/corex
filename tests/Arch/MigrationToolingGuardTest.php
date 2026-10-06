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
        foreach (['packages/core/src', 'tests', 'packages/core/database/migrations/tenant', 'products/demo/database/migrations/budget'] as $dir) {
            $files->makeDirectory($fixture.'/'.$dir, 0755, true);
        }
        $paths = ['packages/core/database/migrations/tenant/0001.php', 'products/demo/database/migrations/budget/0001.php'];
        foreach ($paths as $path) {
            $files->put($fixture.'/'.$path, $migration);
        }
        $files->copy($root.'/pint.json', $fixture.'/pint.json');
        $rector = $files->get($root.'/rector.php');
        $files->put($fixture.'/rector.php', $rector);
        $run = static function (array $command) use ($fixture): Process {
            $process = new Process($command, $fixture, ['PAO_DISABLE' => '1'], timeout: 120);
            $process->run();

            return $process;
        };
        $pint = [PHP_BINARY, $root.'/vendor/bin/pint', '--config='.$fixture.'/pint.json'];
        $rectorCommand = [PHP_BINARY, $root.'/vendor/bin/rector', 'process', 'packages', 'products', '--config='.$fixture.'/rector.php', '--no-progress-bar'];
        foreach ([$pint, $rectorCommand] as $command) {
            $result = $run($command);
            expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput());
            foreach ($paths as $path) {
                expect($files->get($fixture.'/'.$path))->toBe($migration);
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
