<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('PHP API BC selection preserves revision membership and resolvable Internal dependencies', function (string $base, string $candidate, int $expected, string $classification): void {
    $root = dirname(__DIR__, 2);
    $work = sys_get_temp_dir().'/corex-bc-fixture-'.bin2hex(random_bytes(8));
    mkdir($work.'/src', 0777, true);
    file_put_contents($work.'/composer.json', json_encode([
        'name' => 'fixture/php-api-bc', 'description' => 'Isolated BC selection fixture',
        'license' => 'MIT', 'autoload' => ['psr-4' => ['Fixture\\' => 'src/'], 'classmap' => ['src/']],
    ], JSON_THROW_ON_ERROR));
    $run = static function (array $command) use ($work): Process {
        $process = new Process($command, $work, timeout: 60);
        $process->run();

        return $process;
    };

    try {
        expect($run(['git', 'init', '-q'])->isSuccessful())->toBeTrue();
        expect($run(['git', 'config', 'user.email', 'fixture@corex.local'])->isSuccessful())->toBeTrue();
        expect($run(['git', 'config', 'user.name', 'Fixture'])->isSuccessful())->toBeTrue();
        file_put_contents($work.'/src/Port.php', '<?php '.$base);
        expect($run(['git', 'add', '.'])->isSuccessful())->toBeTrue();
        expect($run(['git', 'commit', '-qm', 'base'])->isSuccessful())->toBeTrue();
        $baseSha = trim($run(['git', 'rev-parse', 'HEAD'])->getOutput());
        file_put_contents($work.'/src/Port.php', '<?php '.$candidate);
        expect($run(['git', 'add', '.'])->isSuccessful())->toBeTrue();
        expect($run(['git', 'commit', '--allow-empty', '-qm', 'candidate'])->isSuccessful())->toBeTrue();
        $process = $run([PHP_BINARY, $root.'/scripts/bc/roave.php', '--from='.$baseSha, '--to=HEAD', '--format=json']);
        expect($process->getExitCode())->toBe($expected, $process->getOutput().$process->getErrorOutput());
        $classifier = new Process(['python3', $root.'/scripts/bc/classify.py', (string) $expected], $work);
        $classifier->setInput($process->getOutput().$process->getErrorOutput());
        $classifier->mustRun();
        expect(trim($classifier->getOutput()))->toBe($classification);

        if ($classification === 'bc-break') {
            expect($process->getOutput().$process->getErrorOutput())->toMatch('/deleted|was added/');
        }
    } finally {
        (new Filesystem)->remove($work);
    }
})->with([
    'Internal dependency signature' => [
        'namespace Fixture; #[\CoreX\Attributes\Api] class Port extends Internal\Base {} namespace Fixture\Internal; /** @internal */ class Base { public function get(): string {} } /** @internal */ final class Row { public function value(): int {} }',
        'namespace Fixture; #[\CoreX\Attributes\Api] class Port extends Internal\Base {} namespace Fixture\Internal; /** @internal */ class Base { public function get(): string {} } /** @internal */ final class Row { public function value(): string {} }', 0, 'compatible',
    ],
    'deleted Api' => ['namespace Fixture; #[\CoreX\Attributes\Api] interface Port {}', 'namespace Fixture;', 3, 'bc-break'],
    'deleted Spi' => ['namespace Fixture; #[\CoreX\Attributes\Spi] interface Port {}', 'namespace Fixture;', 3, 'bc-break'],
    'required Implement member' => [
        'namespace Fixture; #[\CoreX\Attributes\Spi] interface Port {}',
        'namespace Fixture; #[\CoreX\Attributes\Spi] interface Port { public function run(): void; }', 3, 'bc-break',
    ],
    'add callable Api member' => [
        'namespace Fixture; #[\CoreX\Attributes\Api] interface Port {}',
        'namespace Fixture; #[\CoreX\Attributes\Api] interface Port { public function run(): void; }', 0, 'compatible',
    ],
    'missing dependency stays infrastructure RED' => [
        'namespace Fixture; #[\CoreX\Attributes\Api] class Port extends Missing {}',
        'namespace Fixture; #[\CoreX\Attributes\Api] class Port extends Missing {}', 3, 'infrastructure',
    ],
]);
