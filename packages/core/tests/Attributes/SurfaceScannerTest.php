<?php

declare(strict_types=1);

namespace CoreX\Tests\Attributes;

use CoreX\Attributes\Tooling\SurfaceReport;
use CoreX\Attributes\Tooling\SurfaceScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class SurfaceScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/corex-attributes-'.bin2hex(random_bytes(8));
        foreach (['demo' => 'Demo', 'foreign' => 'Foreign'] as $package => $namespace) {
            mkdir($this->root.'/packages/'.$package.'/src', 0777, true);
            file_put_contents($this->root.'/packages/'.$package.'/composer.json', json_encode(['name' => 'test/'.$package, 'autoload' => ['psr-4' => [$namespace.'\\' => 'src/']]], JSON_THROW_ON_ERROR));
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem)->remove($this->root);
    }

    private function source(string $source, string $package = 'demo', string $file = 'Surface.php'): void
    {
        file_put_contents($this->root.'/packages/'.$package.'/src/'.$file, '<?php '.$source);
    }

    private function scan(): SurfaceReport
    {
        return (new SurfaceScanner)->scan($this->root);
    }

    public function test_attributes_scanner_resolves_aliases_kinds_and_does_not_execute_sources(): void
    {
        $this->source(<<<'SOURCE'
        namespace Demo;
        use CoreX\Attributes\{Api as PublicApi, Spi, SpiKind};
        throw new \RuntimeException('source must not run');
        #[PublicApi] enum Status: string { case Active = 'active'; }
        #[PublicApi] final readonly class View {
            public function __construct(public string $id, public Status $status) {}
        }
        #[Spi(kind: SpiKind::Implement)] abstract class Driver {
            abstract protected function hook(string $key = 'default'): View;
            public function call(View $view): void {}
        }
        SOURCE);
        $result = $this->scan();
        self::assertSame([], $result->diagnostics);
        $symbols = $result->manifests['test/demo']['symbols'];
        self::assertSame('spi-implement', $symbols['Demo\\Driver']['layer']);
        self::assertSame('protected', $symbols['Demo\\Driver']['methods']['hook']['visibility']);
        self::assertSame('id', $symbols['Demo\\View']['methods']['__construct']['parameters'][0]['name']);
        self::assertSame('string', $symbols['Demo\\Status']['enum_backing']);
        self::assertSame("'active'", $symbols['Demo\\Status']['enum_cases']['Active']);
    }

    public function test_attributes_scanner_resolves_native_throws_without_treating_exception_state_as_payload(): void
    {
        $this->source(<<<'SOURCE'
        namespace Demo;
        use CoreX\Attributes\Api;
        #[Api] final class Rejected extends \RuntimeException {}
        #[Api] interface Query {
            /** @throws \RuntimeException */ public function get(): string;
        }
        SOURCE);
        self::assertSame([], $this->scan()->diagnostics);
        $this->source('namespace Demo; #[\CoreX\Attributes\Api] interface Query { /** @throws UnknownError */ public function get(): string; }');
        self::assertContains('API-UNRESOLVED-TYPE', array_column($this->scan()->diagnostics, 'code'));
    }

    public function test_attributes_class_strings_reference_external_contracts_without_embedding_them(): void
    {
        $this->source(<<<'SOURCE'
        namespace Demo;
        #[\CoreX\Attributes\Api] final readonly class Options {
            /** @param class-string<\Psr\Clock\ClockInterface> $clock */
            public function __construct(public string $clock) {}
        }
        SOURCE);
        self::assertSame([], $this->scan()->diagnostics);

        $this->source(<<<'SOURCE'
        namespace Demo;
        #[\CoreX\Attributes\Api] final readonly class Options {
            public function __construct(public \Psr\Clock\ClockInterface $clock) {}
        }
        SOURCE);
        self::assertContains('API-PAYLOAD', array_column($this->scan()->diagnostics, 'code'));
    }

    public function test_attributes_scanner_checks_nested_imported_aliases_and_promoted_property_docs(): void
    {
        $this->source(<<<'SOURCE'
        namespace Demo;
        use CoreX\Attributes\Api;
        /**
         * @phpstan-type Leaf null|int|string
         * @phpstan-type Values array<string, Leaf|list<Leaf>>
         */
        #[Api] final readonly class Types {}
        /** @phpstan-import-type Values from Types */
        #[Api] final readonly class View {
            public function __construct(/** @var Values */ public array $values) {}
        }
        SOURCE);
        self::assertSame([], $this->scan()->diagnostics);
        $this->source(<<<'SOURCE'
        namespace Demo;
        use CoreX\Attributes\Api;
        /** @phpstan-type Values array<string, \Illuminate\Database\Eloquent\Model> */
        #[Api] final readonly class Types {}
        /** @phpstan-import-type Values from Types */
        #[Api] final readonly class View {
            public function __construct(/** @var Values */ public array $values) {}
        }
        SOURCE);
        self::assertContains('API-PAYLOAD', array_column($this->scan()->diagnostics, 'code'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function negatives(): iterable
    {
        $api = 'namespace Demo; use CoreX\\Attributes\\{Api, Spi, SpiKind}; ';
        yield 'unclassified' => [$api.'final class Loose {}', 'API-UNCLASSIFIED'];
        yield 'conflicting' => [$api.'#[Api, Spi(SpiKind::Call)] final class Loose {}', 'API-METADATA'];
        yield 'duplicate' => [$api.'#[Api, Api] final class Loose {}', 'API-METADATA'];
        yield 'kind' => [$api."#[Spi(kind: 'call')] interface Driver {}", 'API-METADATA'];
        yield 'version' => [$api."#[Api(since: 'latest')] interface View {}", 'API-METADATA'];
        yield 'internal' => ['namespace Demo\\Internal; #[\\CoreX\\Attributes\\Api] final class Row {}', 'API-INTERNAL-ANNOTATION'];
        yield 'mutable' => [$api.'#[Api] class View { public string $id; }', 'API-MUTABLE-DATA'];
        yield 'untyped array' => [$api.'#[Api] final readonly class View { public ?array $rows; }', 'API-UNTYPED-PAYLOAD'];
        yield 'bare list return' => [$api.'#[Api] interface View { /** @return list */ public function rows(): array; }', 'API-UNTYPED-PAYLOAD'];
        yield 'bare non-empty list parameter' => [$api.'#[Api] interface View { /** @param non-empty-list $rows */ public function accept(array $rows): void; }', 'API-UNTYPED-PAYLOAD'];
        yield 'bare non-empty array return' => [$api.'#[Api] interface View { /** @return non-empty-array */ public function rows(): array; }', 'API-UNTYPED-PAYLOAD'];
        yield 'nested bare list' => [$api.'#[Api] interface View { /** @return array{rows: list} */ public function rows(): array; }', 'API-UNTYPED-PAYLOAD'];
        yield 'nested bare array' => [$api.'#[Api] interface View { /** @return list<array> */ public function rows(): array; }', 'API-UNTYPED-PAYLOAD'];
        yield 'mixed' => [$api.'#[Api] final readonly class View { public mixed $value; }', 'API-UNTYPED-PAYLOAD'];
        yield 'exception model field' => [$api.'#[Api] final class Failure extends \RuntimeException { public function __construct(public \Illuminate\Database\Eloquent\Model $row) {} }', 'API-PAYLOAD'];
        yield 'nested SPI mixed field' => [$api.'#[Spi(SpiKind::Call)] final readonly class Data { /** @var array<string, mixed> */ public array $values; } #[Api] final readonly class View { public Data $data; }', 'API-UNTYPED-PAYLOAD'];
        yield 'model' => [$api.'#[Api] final readonly class View { public \Illuminate\Database\Eloquent\Model $row; }', 'API-PAYLOAD'];
        yield 'derived model' => [$api.'class Local extends \Illuminate\Database\Eloquent\Model {} #[Api] final readonly class View { public Local $row; }', 'API-PAYLOAD'];
        yield 'nested foreign internal' => [$api.'#[Api] interface View { /** @return list<array{child: \Foreign\Internal\Row}> */ public function rows(): array; }', 'API-INTERNAL-TYPE'];
        yield 'collection' => [$api.'#[Api] interface View { /** @return \Illuminate\Support\Collection<int, \Foreign\Internal\Row> */ public function rows(): array; }', 'API-PAYLOAD'];
        yield 'unknown alias' => [$api.'#[Api] interface View { /** @return MissingAlias */ public function row(): array; }', 'API-UNRESOLVED-TYPE'];
        yield 'malformed doc' => [$api.'#[Api] interface View { /** @return list< */ public function row(): array; }', 'API-UNRESOLVED-TYPE'];
        yield 'function loophole' => ['namespace Demo; function loose(): void {}', 'API-FUNCTION'];
        yield 'member target' => [$api.'#[Api] final class View { #[Api] public function f(): void {} }', 'API-METADATA'];
    }

    #[DataProvider('negatives')]
    public function test_attributes_scanner_diagnostics_have_location_and_nested_path(string $source, string $code): void
    {
        $this->source($source);
        $this->source('namespace Foreign\Internal; final readonly class Row { public string $id; }', 'foreign');
        $found = array_values(array_filter($this->scan()->diagnostics, static fn ($d): bool => $d->code === $code));
        self::assertNotEmpty($found, $code);
        self::assertStringStartsWith('packages/', $found[0]->file);
        self::assertGreaterThan(0, $found[0]->line);
        self::assertNotSame('', $found[0]->symbol);
        self::assertNotSame('', $found[0]->path);

        if ($code === 'API-INTERNAL-TYPE') {
            self::assertStringContainsString('genericTypes', $found[0]->path);
        }
    }

    public function test_attributes_scanner_checks_all_import_spellings_and_product_consumers(): void
    {
        $this->source('namespace Foreign; class Loose {} #[\CoreX\Attributes\Api] interface Port {}', 'foreign');
        $this->source('namespace Demo; use Foreign\Loose as A; use Foreign\{Loose as B}; #[\CoreX\Attributes\Api] final class Consumer implements \Foreign\Port { public function f(A $a, B $b, \Foreign\Loose $c): void {} }');
        $report = $this->scan();
        $imports = array_filter($report->diagnostics, static fn ($d): bool => $d->code === 'API-IMPORT' && str_contains($d->path, 'parameters'));
        self::assertCount(3, $imports);
        self::assertContains('API-IMPLEMENT', array_column($report->diagnostics, 'code'));
        mkdir($this->root.'/products');
        rename($this->root.'/packages/demo', $this->root.'/products/demo');
        $report = $this->scan();
        self::assertArrayNotHasKey('test/demo', $report->manifests);
        self::assertContains('API-IMPLEMENT', array_column($report->diagnostics, 'code'));
    }

    public function test_attributes_scanner_captures_inherited_signature_and_ignores_bodies_and_prose(): void
    {
        $base = 'namespace Demo; use CoreX\\Attributes\\{Api,Spi,SpiKind}; #[Spi(SpiKind::Implement)] abstract class Base { /** useful prose */ public function run(string $key = "a"): string { return $key; } } #[Api] final class Child extends Base {}';
        $this->source($base);
        $first = $this->scan()->manifests;
        self::assertArrayHasKey('run', $first['test/demo']['symbols']['Demo\\Child']['methods']);
        $this->source(str_replace(['useful prose', 'return $key;'], ['other prose', 'return strtoupper($key);'], $base));
        self::assertSame($first, $this->scan()->manifests);
        $this->source(str_replace('$key', '$name', $base));
        self::assertNotSame($first, $this->scan()->manifests);
    }

    public function test_attributes_scanner_handles_bound_templates_aliases_virtual_members_and_throws(): void
    {
        $this->source(<<<'SOURCE'
        namespace Demo;
        use CoreX\Attributes\Api;
        #[Api] final readonly class Item { public string $id; }
        /**
         * @phpstan-type Items list<array{item: Item}>
         * @template T of Item
         * @method Item lookup(string $id)
         * @property-read Item $item
         */
        #[Api] interface Query {
            /** @return Items */ public function items(): array;
            /** @return T */ public function one(): Item;
        }
        SOURCE);
        self::assertSame([], $this->scan()->diagnostics);
    }

    public function test_attributes_cli_is_deterministic_and_separates_debt_from_parser_errors(): void
    {
        $this->source('namespace Demo; #[\CoreX\Attributes\Api] enum Flag: string { case On = "on"; } class Loose {}');
        self::assertSame(0, $this->cli('--report')->getExitCode());
        self::assertSame(1, $this->cli('--strict')->getExitCode());
        self::assertSame(0, $this->cli('--write')->getExitCode());
        $file = $this->root.'/packages/demo/resources/contracts/php-api.json';
        $bytes = file_get_contents($file);
        self::assertSame(0, $this->cli('--write')->getExitCode());
        self::assertSame($bytes, file_get_contents($file));
        self::assertSame(0, $this->cli('--check')->getExitCode());
        file_put_contents($file, str_replace('Demo\\\\Flag', 'Demo\\\\Invented', $bytes));
        self::assertSame(1, $this->cli('--check')->getExitCode());
        $this->cli('--write');
        $this->source('namespace Demo; #[\CoreX\Attributes\Api] enum Flag: string { case On = "changed"; }');
        self::assertSame(1, $this->cli('--check')->getExitCode());
        $this->source('namespace Demo; class {');
        self::assertSame(2, $this->cli('--report')->getExitCode());
    }

    public function test_attributes_scanner_finds_new_types_without_classmap_and_rejects_duplicate_owner(): void
    {
        $this->source('namespace Demo; #[\CoreX\Attributes\Api] interface Fresh {}');
        self::assertArrayHasKey('Demo\\Fresh', $this->scan()->owners);
        $this->source('namespace Demo; interface Fresh {}', 'foreign');
        $process = $this->cli('--report');
        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('Duplicate FQCN', $process->getErrorOutput());
    }

    public function test_attributes_imported_alias_retains_its_defining_namespace_and_nested_leak(): void
    {
        $this->source('namespace Foreign; /** @phpstan-type Rows list<array{row: Internal\\Row}> */ #[\\CoreX\\Attributes\\Api] interface Types {}', 'foreign');
        $this->source('namespace Foreign\\Internal; final readonly class Row { public string $id; }', 'foreign', 'Row.php');
        $this->source('namespace Demo; /** @phpstan-import-type Rows from \\Foreign\\Types */ #[\\CoreX\\Attributes\\Api] interface Query { /** @return Rows */ public function rows(): array; }');
        $diagnostics = $this->scan()->diagnostics;
        $leaks = array_filter($diagnostics, static fn ($d): bool => $d->symbol === 'Demo\\Query' && $d->code === 'API-INTERNAL-TYPE');
        self::assertNotEmpty($leaks);
        self::assertStringContainsString('Foreign\\Internal\\Row', reset($leaks)->message);
    }

    public function test_attributes_property_hooks_and_default_changes_are_snapshot_inputs(): void
    {
        $source = 'namespace Demo; #[\\CoreX\\Attributes\\Spi(\\CoreX\\Attributes\\SpiKind::Implement)] interface Port { public string $name { get; } public function run(string $key = "a"): void; }';
        $this->source($source);
        $first = $this->scan();
        self::assertSame([], $first->diagnostics);
        self::assertArrayHasKey('get', $first->manifests['test/demo']['symbols']['Demo\\Port']['properties']['name']['hooks']);
        $this->source(str_replace('"a"', '"b"', $source));
        self::assertNotSame($first->manifests, $this->scan()->manifests);
    }

    public function test_attributes_protected_inheritance_and_inherited_internal_leak(): void
    {
        $this->source('namespace Demo; use CoreX\\Attributes\\{Api,Spi,SpiKind}; #[Api] abstract class Base { protected function hook(): void {} /** @return \\Foreign\\Internal\\Row */ public function row(): object {} } #[Spi(SpiKind::Implement)] abstract class Driver extends Base {} #[Api] final class Child extends Base {}');
        $this->source('namespace Foreign\\Internal; final readonly class Row {}', 'foreign');
        $report = $this->scan();
        self::assertArrayHasKey('hook', $report->manifests['test/demo']['symbols']['Demo\\Driver']['methods']);
        self::assertArrayNotHasKey('hook', $report->manifests['test/demo']['symbols']['Demo\\Child']['methods']);
        self::assertNotEmpty(array_filter($report->diagnostics, static fn ($d): bool => $d->symbol === 'Demo\\Child' && $d->code === 'API-INTERNAL-TYPE'));
    }

    public function test_attributes_nested_mixed_and_nullable_object_cannot_hide_payloads(): void
    {
        $this->source('namespace Demo; #[\\CoreX\\Attributes\\Api] final readonly class View { /** @var list<array{data: mixed}> */ public array $rows; public ?object $other; }');
        self::assertGreaterThanOrEqual(2, count(array_filter($this->scan()->diagnostics, static fn ($d): bool => $d->code === 'API-UNTYPED-PAYLOAD')));
    }

    public function test_attributes_typed_collection_docs_remain_valid(): void
    {
        foreach (['list<int>', 'non-empty-list<string>', 'non-empty-array<string, int>', 'array{rows: list<string>}', 'list<array{id: int}>', 'iterable<int>'] as $doc) {
            $this->source('namespace Demo; #[\\CoreX\\Attributes\\Api] interface View { /** @return '.$doc.' */ public function rows(): array; }');
            self::assertSame([], $this->scan()->diagnostics, $doc);
        }
    }

    public function test_attributes_configuration_class_strings_and_spi_model_documentation_are_checked(): void
    {
        $this->source('namespace Foreign\\Internal; final class Secret {}', 'foreign');
        $this->source('namespace Demo; #[\\CoreX\\Attributes\\Spi(\\CoreX\\Attributes\\SpiKind::Call)] interface Extension { public function use(\\Illuminate\\Database\\Eloquent\\Model $model): void; }');
        mkdir($this->root.'/packages/demo/config');
        file_put_contents($this->root.'/packages/demo/config/module.php', '<?php return ["type" => "Foreign\\\\Internal\\\\Secret"];');
        $report = $this->scan();
        self::assertContains('API-MODEL-SPI', array_column($report->diagnostics, 'code'));
        self::assertNotEmpty(array_filter($report->diagnostics, static fn ($d): bool => $d->file === 'packages/demo/config/module.php' && $d->code === 'API-INTERNAL-TYPE'));
    }

    public function test_attributes_composition_policy_requires_the_exact_existing_edge(): void
    {
        $this->source('namespace Foreign\\Internal; final class Row {}', 'foreign');
        $this->source('namespace Demo; #[\\CoreX\\Attributes\\Spi(\\CoreX\\Attributes\\SpiKind::Call)] final class Composition { public function build(): void { new \\Foreign\\Internal\\Row; } }');
        mkdir($this->root.'/scripts');
        $edge = ['from' => 'test/demo', 'to' => 'test/foreign', 'import_file' => 'packages/demo/src/Surface.php', 'symbol' => 'Foreign\\Internal\\Row', 'target_file' => 'packages/foreign/src/Surface.php'];
        $config = $this->root.'/scripts/package-graph-gate.json';
        file_put_contents($config, json_encode(['internal_composition_edges' => [$edge]], JSON_THROW_ON_ERROR));
        self::assertSame([], $this->scan()->diagnostics);
        $edge['symbol'] = 'Foreign\\Internal\\Other';
        file_put_contents($config, json_encode(['internal_composition_edges' => [$edge]], JSON_THROW_ON_ERROR));
        self::assertContains('API-INTERNAL-TYPE', array_column($this->scan()->diagnostics, 'code'));
        file_put_contents($config, '{"internal_composition_edges":["invalid"]}');
        self::assertSame(2, $this->cli('--report')->getExitCode());
    }

    public function test_attributes_manifest_keeps_own_and_local_members_without_expanding_external_types(): void
    {
        $this->source(<<<'SOURCE'
        namespace Foreign;
        use CoreX\Attributes\{Spi, SpiKind};
        #[Spi(SpiKind::Implement)] class Base {
            public const EXTERNAL = 1;
            public string $externalProperty;
            public function morph(string $key): string { return $key; }
            public function overridden(): string { return 'external'; }
        }
        #[Spi(SpiKind::Call)] trait ExternalTrait { public function externalTrait(): void {} }
        #[Spi(SpiKind::Implement)] interface ExternalPort { public function port(): void; }
        SOURCE, 'foreign');
        $own = <<<'SOURCE'
        namespace Demo;
        use CoreX\Attributes\{Api, Spi, SpiKind};
        #[Spi(SpiKind::Call)] trait LocalTrait {
            public const LOCAL = 2;
            public string $localProperty;
            public function localTrait(): void {}
        }
        #[Spi(SpiKind::Implement)] class Base extends \Foreign\Base {
            public const OWN = 3;
            public string $ownProperty;
            public function own(): int { return 1; }
        }
        #[Spi(SpiKind::Implement)] final class Child extends Base implements \Foreign\ExternalPort {
            use LocalTrait, \Foreign\ExternalTrait;
            public function __construct(public string $promoted) {}
            public function overridden(): string { return 'own'; }
            public function port(): void {}
        }
        #[Api] enum Status: string { case On = 'on'; }
        SOURCE;
        $this->source($own);
        $first = $this->scan()->manifests['test/demo'];
        $child = $first['symbols']['Demo\\Child'];
        self::assertSame(['Demo\\Base'], $child['extends']);
        self::assertSame(['Foreign\\ExternalPort'], $child['implements']);
        self::assertSame(['Demo\\LocalTrait', 'Foreign\\ExternalTrait'], $child['uses']);
        self::assertArrayNotHasKey('morph', $child['methods']);
        self::assertArrayNotHasKey('externalTrait', $child['methods']);
        self::assertArrayNotHasKey('externalProperty', $child['properties']);
        self::assertArrayNotHasKey('EXTERNAL', $child['constants']);
        self::assertSame(['__construct', 'localTrait', 'overridden', 'own', 'port'], array_keys($child['methods']));
        self::assertSame(['localProperty', 'ownProperty', 'promoted'], array_keys($child['properties']));
        self::assertSame(['LOCAL', 'OWN'], array_keys($child['constants']));
        self::assertSame('string', $child['methods']['overridden']['return']);
        self::assertSame(['On' => "'on'"], $first['symbols']['Demo\\Status']['enum_cases']);
        // Change external signatures and PHPDoc, not merely executable bodies.
        $external = (string) file_get_contents($this->root.'/packages/foreign/src/Surface.php');
        file_put_contents($this->root.'/packages/foreign/src/Surface.php', str_replace('public function morph(string $key): string', '/** @throws \\RuntimeException */ public function morph(int $key): string', $external));
        self::assertSame($first, $this->scan()->manifests['test/demo']);
    }

    public function test_attributes_public_promoted_property_survives_a_private_constructor(): void
    {
        $this->source('namespace Demo; #[\CoreX\Attributes\Api] final readonly class View { private function __construct(public string $id) {} }');
        $report = $this->scan();
        self::assertSame([], $report->diagnostics);
        $view = $report->manifests['test/demo']['symbols']['Demo\\View'];
        self::assertArrayNotHasKey('__construct', $view['methods']);
        self::assertSame('string', $view['properties']['id']['type']);
        self::assertSame('public', $view['properties']['id']['visibility']);
    }

    private function cli(string $mode): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 4).'/scripts/php-api.php', '--root='.$this->root, $mode, '--format=json']);
        $process->run();

        return $process;
    }
}
