<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @param array<string, string> $sources */
function unusedPublicFixture(array $sources, Closure $test): void
{
    $root = sys_get_temp_dir().'/corex-unused-public-'.bin2hex(random_bytes(8));
    $files = new Filesystem;

    try {
        foreach ($sources as $path => $source) {
            $files->ensureDirectoryExists(dirname($root.'/'.$path));
            $files->put($root.'/'.$path, $source);
        }
        $files->ensureDirectoryExists($root.'/scripts/quality');
        $files->put($root.'/scripts/quality/public-entrypoints.json', json_encode(['schema' => 'unused-public-entrypoints/2', 'entries' => []], JSON_THROW_ON_ERROR));
        $run = static function () use ($root): Process {
            $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/quality/unused-public.php', '--check', '--root='.$root]);
            $process->run();

            return $process;
        };
        $test($root, $files, $run);
    } finally {
        $files->deleteDirectory($root);
    }
}

/** @return array<string, mixed> */
function unusedPublicEntrypoint(string $root, string $class, string $method, string $kind = 'external-api'): array
{
    return [
        'symbol' => $class.'::'.$method,
        'source' => 'packages/demo/src/Entry.php',
        'source_sha256' => hash_file('sha256', $root.'/packages/demo/src/Entry.php'),
        'kind' => $kind,
        'reason' => 'Applications invoke this documented entrypoint through the public extension contract.',
        'root' => ['path' => 'packages/demo/README.md', 'anchor' => $class.'::'.$method, 'sha256' => hash_file('sha256', $root.'/packages/demo/README.md')],
        'expires' => null,
    ];
}

it('rejects unused Internal methods and ignores unrelated receivers, comments and member manifests', function (): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo\Internal; final class Entry { public function unused(): void {} }',
        'packages/demo/tests/use.php' => '<?php $unrelated->unused(); // Demo\\Internal\\Entry::unused',
        'packages/demo/resources/contracts/php-api.json' => '{"symbols":{"Demo\\\\Internal\\\\Entry":{"methods":{"unused":{}}}}}',
    ], function (string $root, Filesystem $files, Closure $run): void {
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('UNUSED-PUBLIC Demo\\Internal\\Entry::unused');
        $files->put($root.'/packages/demo/tests/use.php', '<?php (new \\Demo\\Internal\\Entry)->unused();');
        expect($run()->getExitCode())->toBe(0);
    });
});

it('resolves typed DI receivers, interface implementations, callable registrations and manifest invocations', function (): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo\Internal; interface Contract { public function run(): void; } final class Entry implements Contract { public function run(): void {} public function callback(): void {} public function manifest(): void {} }',
        'packages/demo/tests/use.php' => '<?php function consume(\\Demo\\Internal\\Contract $service): void { $service->run(); } app(\\Demo\\Internal\\Entry::class)->run();',
        'packages/demo/config/services.php' => '<?php return ["handler" => [\\Demo\\Internal\\Entry::class, "callback"]];',
        'products/demo/config/manifest.json' => '{"handler":"Demo\\\\Internal\\\\Entry::manifest"}',
    ], function (string $root, Filesystem $files, Closure $run): void {
        expect($run()->getExitCode())->toBe(0);
        $files->put($root.'/packages/demo/config/services.php', '<?php return ["method" => "callback"];');
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('UNUSED-PUBLIC Demo\\Internal\\Entry::callback');
    });
});

it('admits exact documented Api and Spi roots and rejects stale, expired, wildcard and missing roots', function (string $attribute, string $kind): void {
    $class = 'Demo\\Entry';
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo; use Packman\Attributes\\'.$attribute.'; #['.$attribute.'] final class Entry { public function external(): void {} }',
        'packages/demo/README.md' => 'Applications call Demo\\Entry::external to enter the supported contract.',
    ], function (string $root, Filesystem $files, Closure $run) use ($class, $kind): void {
        $entry = unusedPublicEntrypoint($root, $class, 'external', $kind);
        $write = static function (array $rows) use ($root, $files): void {
            $files->put($root.'/scripts/quality/public-entrypoints.json', json_encode(['schema' => 'unused-public-entrypoints/2', 'entries' => $rows], JSON_THROW_ON_ERROR));
        };
        $write([$entry]);
        expect($run()->getExitCode())->toBe(0);
        foreach (['symbol' => $class.'::*', 'expires' => '2000-01-01', 'source_sha256' => str_repeat('0', 64)] as $key => $invalid) {
            $bad = $entry;
            $bad[$key] = $invalid;
            $write([$bad]);
            expect($run()->getExitCode())->toBe(1);
        }
        $write([$entry]);
        $files->put($root.'/packages/demo/README.md', 'Removed the external contract.');
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('ENTRYPOINT-STALE');
        $files->delete($root.'/packages/demo/README.md');
        expect($run()->getExitCode())->toBe(1);
    });
})->with([['Api', 'external-api'], ['Spi', 'external-spi']]);

it('checks every ledger row even when a local call makes the exception unnecessary', function (): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo; use Packman\Attributes\Api; #[Api] final class Entry { public function external(): void {} }',
        'packages/demo/tests/use.php' => '<?php (new \\Demo\\Entry)->external();',
        'packages/demo/README.md' => 'Applications call Demo\\Entry::external.',
    ], function (string $root, Filesystem $files, Closure $run): void {
        $entry = unusedPublicEntrypoint($root, 'Demo\\Entry', 'external');
        $entry['source_sha256'] = str_repeat('0', 64);
        $files->put($root.'/scripts/quality/public-entrypoints.json', json_encode(['schema' => 'unused-public-entrypoints/2', 'entries' => [$entry]], JSON_THROW_ON_ERROR));
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('ENTRYPOINT-STALE');
    });
});

it('fails closed for invalid PHP, missing ledger and malformed manifest input', function (): void {
    unusedPublicFixture(['packages/demo/src/Entry.php' => '<?php final class Broken {'], function (string $root, Filesystem $files, Closure $run): void {
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('SOURCE-PARSE');
        $files->put($root.'/packages/demo/src/Entry.php', '<?php namespace Demo; final class Entry { public function run(): void {} } (new Entry)->run();');
        $files->ensureDirectoryExists($root.'/packages/demo/config');
        $files->put($root.'/packages/demo/config/manifest.json', '{broken');
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('MANIFEST-PARSE');
        $files->delete($root.'/packages/demo/config/manifest.json');
        $files->delete($root.'/scripts/quality/public-entrypoints.json');
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('ENTRYPOINT-CONFIG');
    });
});

it('checks public anonymous source methods and resolves factory and generic registry receiver types', function (): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo\Internal; interface Contract { public function run(): void; } final class Registry { /** @var list<Contract> */ private array $services = []; public function run(): void { foreach ($this->services as $service) { $service->run(); } } } function factory(): Registry { return new Registry; } factory()->run(); return new class implements Contract { public function run(): void {} public function unused(): void {} };',
    ], function (string $root, Filesystem $files, Closure $run): void {
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('Anonymous\\Source_')->toContain('::unused');
        $source = $files->get($root.'/packages/demo/src/Entry.php');
        $files->put($root.'/packages/demo/src/Entry.php', str_replace('public function unused(): void {}', '', $source));
        expect($run()->getExitCode())->toBe(0);
    });
});

it('binds inherited external roots to the exact marked port method', function (): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo; use Packman\Attributes\Spi; #[Spi] interface Port { public function external(): void; } final class Entry implements Port { public function external(): void {} }',
        'packages/demo/README.md' => 'Installed adapters implement Demo\\Port::external; applications call it through Demo\\Port.',
    ], function (string $root, Filesystem $files, Closure $run): void {
        $port = unusedPublicEntrypoint($root, 'Demo\\Port', 'external', 'external-spi');
        $implementation = unusedPublicEntrypoint($root, 'Demo\\Entry', 'external', 'external-implementation');
        $implementation['root']['anchor'] = 'Demo\\Port::external';
        $files->put($root.'/scripts/quality/public-entrypoints.json', json_encode(['schema' => 'unused-public-entrypoints/2', 'entries' => [$port, $implementation]], JSON_THROW_ON_ERROR));
        expect($run()->getExitCode())->toBe(0);
        $source = str_replace('#[Spi]', '', $files->get($root.'/packages/demo/src/Entry.php'));
        $files->put($root.'/packages/demo/src/Entry.php', $source);
        $port['source_sha256'] = hash('sha256', $source);
        $implementation['source_sha256'] = hash('sha256', $source);
        $files->put($root.'/scripts/quality/public-entrypoints.json', json_encode(['schema' => 'unused-public-entrypoints/2', 'entries' => [$port, $implementation]], JSON_THROW_ON_ERROR));
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('ENTRYPOINT-STALE Demo\\Entry::external');
    });
});

it('rejects an absent source contour instead of passing an empty report', function (): void {
    unusedPublicFixture(['packages/demo/README.md' => 'No source declarations.'], function (string $root, Filesystem $files, Closure $run): void {
        expect($run()->getExitCode())->toBe(1)->and($run()->getErrorOutput())->toContain('SOURCE-EMPTY');
    });
});

it('counts callable-return and narrowed receivers on the matching type only', function (string $usage): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => <<<'PHP'
            <?php
            namespace Demo;

            interface Port { public function run(): string; }
            final class Registered implements Port { public function run(): string { return 'registered'; } }
            PHP
            ."\n".$usage,
        'packages/demo/src/Other.php' => '<?php namespace Demo; final class Other { public function run(): string { return "other"; } }',
    ], function (string $root, Filesystem $files, Closure $run): void {
        $result = $run();
        expect($result->getExitCode())->toBe(1)
            ->and($result->getErrorOutput())->toContain('UNUSED-PUBLIC Demo\\Other::run')
            ->not->toContain('UNUSED-PUBLIC Demo\\Registered::run')
            ->not->toContain('UNUSED-PUBLIC Demo\\Port::run');
        $files->delete($root.'/packages/demo/src/Other.php');
        expect($run()->getExitCode())->toBe(0);
    });
})->with([
    'typed callable' => <<<'PHP'
        final class Caller {
            /** @param \Closure(): Port $service */
            public function __construct(private \Closure $service) {}
            public function call(): string { return ($this->service)()->run(); }
        }
        (new Caller(static fn (): Port => new Registered))->call();
        PHP,
    'nullable typed callable' => <<<'PHP'
        final class Caller {
            /** @param \Closure(): ?Port $service */
            public function __construct(private \Closure $service) {}
            public function call(): ?string { return ($this->service)()?->run(); }
        }
        (new Caller(static fn (): ?Port => new Registered))->call();
        PHP,
    'instanceof' => 'function call(mixed $service): ?string { return $service instanceof Port ? $service->run() : null; }',
    'coalesce or throw' => 'function call(?Port $service): string { return ($service ?? throw new \\LogicException)->run(); }',
]);

it('does not mistake first-class closure creation for invoking its typed result', function (): void {
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => <<<'PHP'
            <?php
            namespace Demo;

            final class Value { public function call(): string { return 'unused'; } }
            final class Caller {
                /** @param \Closure(): Value $load */
                public function __construct(private \Closure $load) {}
                public function run(): Value { return ($this->load)(...)->call($this); }
            }
            (new Caller(static fn (): Value => new Value))->run();
            PHP,
    ], function (string $root, Filesystem $files, Closure $run): void {
        $result = $run();
        expect($result->getExitCode())->toBe(1)
            ->and($result->getErrorOutput())->toContain('UNUSED-PUBLIC Demo\\Value::call');
        $source = $files->get($root.'/packages/demo/src/Entry.php');
        $files->put($root.'/packages/demo/src/Entry.php', str_replace("public function call(): string { return 'unused'; }", '', $source));
        expect($run()->getExitCode())->toBe(0);
    });
});

it('follows typed Packman callbacks without exempting unrelated methods or auditing vendor exports', function (): void {
    unusedPublicFixture([
        'vendor/axiomasoft/packman/packages/core/src/Dispatch.php' => '<?php namespace Packman\Fixture; #[\Packman\Attributes\Spi] interface Port { public function execute(): void; } final class Dispatch { public function invoke(Port $port): void { $port->execute(); } }',
        'packages/demo/src/Entry.php' => '<?php namespace Demo; final class Entry implements \Packman\Fixture\Port { public function execute(): void {} } final class Unrelated { public function execute(): void {} }',
    ], function (string $root, Filesystem $files, Closure $run): void {
        $result = $run();
        expect($result->getExitCode())->toBe(1)
            ->and($result->getErrorOutput())->toContain('UNUSED-PUBLIC Demo\\Unrelated::execute')
            ->not->toContain('UNUSED-PUBLIC Demo\\Entry::execute')
            ->not->toContain('UNUSED-PUBLIC Packman\\Fixture\\Dispatch::invoke');
        $source = $files->get($root.'/packages/demo/src/Entry.php');
        $files->put($root.'/packages/demo/src/Entry.php', str_replace('final class Unrelated { public function execute(): void {} }', '', $source));
        expect($run()->getExitCode())->toBe(0);
    });
});

it('proves vendor roots by their anchor and keeps hashing repository roots', function (): void {
    $class = 'Demo\\Internal\\Entry';
    unusedPublicFixture([
        'packages/demo/src/Entry.php' => '<?php namespace Demo\Internal; final class Entry { public function handle(): void {} }',
        'vendor/laravel/framework/src/Illuminate/Console/Command.php' => '<?php $this->laravel->call([$this, $method]);',
        'packages/demo/README.md' => 'Artisan calls Demo\\Internal\\Entry::handle.',
    ], function (string $root, Filesystem $files, Closure $run) use ($class): void {
        $vendor = 'vendor/laravel/framework/src/Illuminate/Console/Command.php';
        $entry = unusedPublicEntrypoint($root, $class, 'handle', 'framework');
        $entry['root'] = ['path' => $vendor, 'anchor' => '$this->laravel->call([$this, $method])'];
        $write = static function (array $row) use ($root, $files): void {
            $files->put($root.'/scripts/quality/public-entrypoints.json', json_encode(['schema' => 'unused-public-entrypoints/2', 'entries' => [$row]], JSON_THROW_ON_ERROR));
        };
        $write($entry);
        expect($run()->getExitCode())->toBe(0);

        $files->put($root.'/'.$vendor, '<?php $this->laravel->call( /* changed in a patch */ [ $this, $method ] );');
        expect($run()->getExitCode())->toBe(0);

        $files->put($root.'/'.$vendor, '<?php // historical invocation: $this->laravel->call([$this, $method])');
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('ENTRYPOINT-STALE');

        $files->put($root.'/'.$vendor, '<?php $this->laravel->call([$this, $method]);');
        $hashed = $entry;
        $hashed['root']['sha256'] = hash_file('sha256', $root.'/'.$vendor);
        $write($hashed);
        expect($run()->getExitCode())->toBe(1);

        $repository = $entry;
        $repository['root'] = ['path' => 'packages/demo/README.md', 'anchor' => $class.'::handle'];
        $write($repository);
        expect($run()->getExitCode())->toBe(1);

        $repository['root']['sha256'] = hash_file('sha256', $root.'/packages/demo/README.md');
        $write($repository);
        expect($run()->getExitCode())->toBe(0);
        $files->put($root.'/packages/demo/README.md', 'Artisan calls Demo\\Internal\\Entry::handle. Edited.');
        expect($run()->getExitCode())->toBe(1);
    });
});
