<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('BC delta admission requires accepted source hashes and the exact base and changes', function (string $format): void {
    $root = dirname(__DIR__, 2);
    $work = sys_get_temp_dir().'/corex-bc-admission-'.bin2hex(random_bytes(8));
    mkdir($work.'/scripts/bc', 0777, true);
    $source = $format === 'json'
        ? json_encode(['$schema' => 'urn:swissknifeman:task:plan:decision:v1', 'status' => 'accepted'], JSON_THROW_ON_ERROR)
        : "---\nstatus: accepted\n---\nApproved finite delta.\n";
    $path = 'decision.'.$format;
    $change = ['description' => 'Approved removal', 'path' => '/src/Port.php', 'modificationType' => 'removed'];
    $rule = ['base_revision' => 'exact-base', 'decisions' => [['path' => $path, 'sha256' => hash('sha256', $source)]], 'changes' => [[
        'description_sha256' => hash('sha256', $change['description']), 'path' => $change['path'], 'modificationType' => $change['modificationType'],
    ]]];
    $write = static function (array $candidate) use ($work): void {
        file_put_contents($work.'/scripts/bc/approved-deltas.json', json_encode(['schema' => 'corex-bc-approved-deltas/1', 'packages' => ['fixture/port' => $candidate]], JSON_THROW_ON_ERROR));
    };
    $run = static function (array $changes, string $base = 'exact-base') use ($root, $work): int {
        $process = new Process(['python3', $root.'/scripts/bc/approved-delta.py', '--root', $work, '--package', 'fixture/port', '--base', $base]);
        $process->setInput(json_encode(['errors' => $changes], JSON_THROW_ON_ERROR)."\n");
        $process->run();

        return $process->getExitCode();
    };

    try {
        file_put_contents($work.'/'.$path, $source);
        $write($rule);
        expect($run([$change]))->toBe(0)
            ->and($run([$change], 'wrong-base'))->toBe(1)
            ->and($run([]))->toBe(1)
            ->and($run([$change, [...$change, 'description' => 'Unapproved removal']]))->toBe(1);
        file_put_contents($work.'/'.$path, $source.' ');
        expect($run([$change]))->toBe(1);
        $rejected = str_replace('accepted', 'open', $source);
        file_put_contents($work.'/'.$path, $rejected);
        $rule['decisions'][0]['sha256'] = hash('sha256', $rejected);
        $write($rule);
        expect($run([$change]))->toBe(1);
        unlink($work.'/'.$path);
        expect($run([$change]))->toBe(1);
    } finally {
        (new Filesystem)->remove($work);
    }
})->with(['json', 'md']);

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
