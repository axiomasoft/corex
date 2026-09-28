<?php

declare(strict_types=1);

use CoreX\Tests\Golden\Support\GoldenSnapshot;
use CoreX\Tests\Golden\Support\Normalizer;
use CoreX\Tests\Golden\Support\RenameMap;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;

$root = getcwd();
require $root.'/vendor/autoload.php';
$check = static function (bool $condition, string $label): void {
    if (! $condition) {
        throw new RuntimeException($label);
    }
    echo $label." OK\n";
};
$map = RenameMap::load($root.'/tests/Golden/rename-map.json');
foreach (glob($root.'/tests/Golden/baseline/*.json') as $file) {
    $raw = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $once = $map->apply($raw);
    $check($map->apply($once) === $once, basename($file).' rename idempotence');
}
$fixtureRoot = sys_get_temp_dir().'/corex-golden-inventory-'.getmypid();
mkdir($fixtureRoot.'/packages/probe/src', 0700, true);
$file = $fixtureRoot.'/packages/probe/src/odd-name.php';
file_put_contents($file, '<?php namespace GoldenInventory\One { final class First { public const RATIO = 1.0; public function run(float $ratio = 1.0): void {} } } namespace GoldenInventory\Two { interface Second {} trait Third {} enum Fourth { case One; } $anonymous = new class {}; $name = Second::class; }');

try {
    $classes = GoldenSnapshot::sourceClasses($fixtureRoot);
    $check($classes === ['GoldenInventory\\One\\First', 'GoldenInventory\\Two\\Fourth', 'GoldenInventory\\Two\\Second', 'GoldenInventory\\Two\\Third'], 'multiple/bracketed declarations; anonymous/class-literal excluded');
    $surface = GoldenSnapshot::phpSurface($classes);
    $bytes = json_encode($surface, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $check(json_decode($bytes, true, flags: JSON_THROW_ON_ERROR) === $surface, 'float constants/defaults JSON round trip');
} finally {
    unlink($file);
    rmdir(dirname($file));
    rmdir(dirname($file, 2));
    rmdir(dirname($file, 3));
    rmdir($fixtureRoot);
}
$value = ['CoreX\\Example\\Internal\\Type' => ['kind' => 'class', 'methods' => [], 'internal' => false, 'interfaces' => ['Z', 'A'], 'constants' => ['LIST' => ['value' => ['z' => ['b' => 1, 'a' => 2], 'a' => 0], 'type' => 'array']]]];
$normalized = Normalizer::normalize($value);
$check($normalized['CoreX\\Example\\Internal\\Type']['internal'] && $normalized['CoreX\\Example\\Internal\\Type']['interfaces'] === ['A', 'Z'], 'derived Internal flag and interface order');
$check($normalized['CoreX\\Example\\Internal\\Type']['constants']['LIST']['value'] === $value['CoreX\\Example\\Internal\\Type']['constants']['LIST']['value'], 'constant nested insertion order preserved');
$operation = ['id' => 'test', 'request_schema' => ['properties' => ['contract_hash' => ['const' => 'schema-value'], 'generated_at' => ['type' => 'string'], 'files' => ['type' => 'array']]], 'response_schema' => [], 'contract_hash' => 'derived'];
$first = Normalizer::normalize(['operations' => [$operation]]);
$operation['contract_hash'] = 'rename-derived';
$check(Normalizer::normalize(['operations' => [$operation]]) === $first, 'operation derived hash ignored');
$operation['request_schema']['properties']['contract_hash']['const'] = 'drift';
$check(Normalizer::normalize(['operations' => [$operation]]) !== $first, 'schema same-name fields detect drift');
Facade::setFacadeApplication(new Container);
foreach (['failed', 'missing'] as $mode) {
    $tmp = sys_get_temp_dir().'/corex-golden-wire-control-'.$mode.'-'.getmypid();
    $mock = Mockery::mock();
    $mock->shouldReceive('call')->once()->andReturnUsing(static function () use ($tmp, $mode): int {
        mkdir($tmp);
        file_put_contents($tmp.'/errors.json', '[]');

        return $mode === 'failed' ? 1 : 0;
    });

    if ($mode === 'failed') {
        $mock->shouldReceive('output')->once()->andReturn('controlled failure');
    }
    Artisan::swap($mock);

    try {
        GoldenSnapshot::wire($tmp, '/unused-fixture');

        throw new LogicException('wire accepted '.$mode);
    } catch (RuntimeException $e) {
        $check(str_contains($e->getMessage(), $mode === 'failed' ? 'Snapshot command failed' : 'Missing snapshot'), 'wire '.$mode.' rejected');
    }
    $check(! file_exists($tmp), 'wire '.$mode.' temporary directory cleaned');
    Mockery::close();
}
Facade::clearResolvedInstances();
