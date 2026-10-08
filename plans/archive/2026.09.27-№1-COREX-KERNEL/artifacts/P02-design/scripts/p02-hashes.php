<?php

declare(strict_types=1);
use CoreX\Api\Internal\ToolContractHasher;
use CoreX\Api\Tests\Support\ArchiveToolModule;
use CoreX\Api\Tests\Support\LabToolModule;
use CoreX\Api\ToolSectionCompiler;
use CoreX\Modules\Compiler\RegistryCompiler;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;

/** Capture old tool receipts and derive their hashes after exact name projection. */
require 'vendor/autoload.php';
$plan = json_decode(file_get_contents('build/p02/plan.json'), true, flags: JSON_THROW_ON_ERROR);
$container = new Application(getcwd());
Container::setInstance($container);
$container->instance(RegistryCompiler::class, new RegistryCompiler(sections: [new ToolSectionCompiler]));
$receipts = json_decode(file_get_contents('packages/operations/tests/fixtures/tool-contribution/selected-contributions.json'), true, flags: JSON_THROW_ON_ERROR);
$compiled = [];
foreach ([ArchiveToolModule::class, LabToolModule::class] as $fixture) {
    foreach ($fixture::compile()->api as $api) {
        if ($api->mode === 'tool') {
            $compiled[$api->toolCode] = $api;
        }
    }
}
$result = [];
foreach ($receipts['contributions'] as $row) {
    if ($row['disposition'] !== 'included') {
        continue;
    }
    $api = $compiled[$row['tool_code']];
    $old = ToolContractHasher::hash($api);

    if ($old !== $row['contract_hash']) {
        throw new RuntimeException('Preimage receipt mismatch: '.$row['id']);
    }
    $handler = $api->handler;
    $api->handler = $plan['all_classes'][$handler] ?? $handler;
    $new = ToolContractHasher::hash($api);
    $result[] = ['id' => $row['id'], 'before' => $old, 'after' => $new, 'old_handler' => $handler, 'new_handler' => $api->handler];
}
file_put_contents('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P02.3-execution/tool-hash-projection.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo 'tool receipt hashes: '.count($result)." verified and projected from their old handlers\n";
