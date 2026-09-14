<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('refuses a non-test PostgreSQL database before bootstrapping the test application', function (): void {
    $root = dirname(__DIR__, 4);
    $process = new Process([
        PHP_BINARY, $root.'/vendor/bin/phpunit', '--bootstrap', $root.'/vendor/autoload.php',
        '--no-configuration', '--filter', 'test_publish_failure', __DIR__.'/../Fixtures/PublishFailureFixture.php',
    ], env: ['PAO_DISABLE' => 'true', 'PGSQL_DATABASE' => 'corex_dev']);
    $process->run();
    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getOutput().$process->getErrorOutput())->toContain('Unsafe test database: PGSQL_DATABASE must end in _test.')
        ->not->toContain('Intentional publish failure');
});

it('cleans failed publish resources and leaves the next SQLite application usable', function (): void {
    $root = dirname(__DIR__, 4);
    $shared = $root.'/vendor/orchestra/testbench-core/laravel/database/migrations';
    $before = [];
    foreach (glob($shared.'/*.php') ?: [] as $file) {
        $before[$file] = hash_file('sha256', $file);
    }

    $process = new Process([
        PHP_BINARY, $root.'/vendor/bin/phpunit', '--bootstrap', $root.'/vendor/autoload.php',
        '--no-configuration', '--order-by=default', __DIR__.'/../Fixtures/PublishFailureFixture.php',
    ]);
    $process->setEnv(['PAO_DISABLE' => 'true']);
    $process->run();
    $output = $process->getOutput().$process->getErrorOutput();

    expect($process->getExitCode())->toBe(1)
        ->and($output)->toContain('Tests: 2', 'Failures: 1', 'Intentional publish failure');
    preg_match('/isolated path: (\S+)/', $output, $matches);
    expect($matches)->toHaveCount(2)
        ->and(is_dir($matches[1]))->toBeFalse();
    foreach ($before as $file => $hash) {
        expect(hash_file('sha256', $file))->toBe($hash);
    }
    expect(glob(database_path('migrations/*.php')))->toBe([]);
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
});
