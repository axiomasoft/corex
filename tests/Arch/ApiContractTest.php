<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

test('operation contract has one compiled authority and one guarded execution boundary', function (): void {
    expect(apiArchitectureViolations())->toBe([]);
});

test('operation contract ratchet detects a second registry and a direct HTTP handler call', function (): void {
    $directory = SourceScanner::packagePath('operations', 'src');
    $registry = $directory.'/RogueOperationRegistry.php';
    $adapter = $directory.'/RogueHttpAdapter.php';
    file_put_contents($registry, '<?php final class RogueOperationRegistry {}');
    file_put_contents($adapter, '<?php final class RogueHttpAdapter { public function run($handler) { return $handler->handle(); } }');

    try {
        expect(apiArchitectureViolations())->toContain('second-operation-registry: packages/operations/src/RogueOperationRegistry.php')
            ->toContain('direct-adapter-handler: packages/operations/src/RogueHttpAdapter.php');
    } finally {
        unlink($registry);
        unlink($adapter);
    }
});

test('operation contract ratchet detects a second invoker and tenant hash input', function (): void {
    $directory = SourceScanner::packagePath('operations', 'src');
    $invoker = $directory.'/RogueOperationInvoker.php';
    $hash = $directory.'/TenantProfileHashProbe.php';
    file_put_contents($invoker, '<?php final class RogueOperationInvoker implements OperationInvoker {}');
    file_put_contents($hash, '<?php $contractFingerprint = hash("sha256", $tenant->id);');

    try {
        expect(apiArchitectureViolations())->toContain('unreviewed-operation-invoker: packages/operations/src/RogueOperationInvoker.php')
            ->toContain('tenant-profile-hash: packages/operations/src/TenantProfileHashProbe.php');
    } finally {
        unlink($invoker);
        unlink($hash);
    }
});

test('operation contract ratchet detects a direct handler path without field policy', function (): void {
    $path = SourceScanner::packagePath('operations', 'src/FieldBypassOperationInvoker.php');
    file_put_contents($path, '<?php final class FieldBypassOperationInvoker implements OperationInvoker { public function invoke($handler) { return $handler->handle(); } }');

    try {
        expect(apiArchitectureViolations())->toContain('field-policy-bypass: packages/operations/src/FieldBypassOperationInvoker.php');
    } finally {
        unlink($path);
    }
});

test('operation contract ratchet detects GraphQL source, dependency, capability and snapshot', function (): void {
    $source = SourceScanner::packagePath('operations', 'src/GraphqlProbe.php');
    $snapshot = SourceScanner::repoRoot().'/api/v2/graphql.json';
    file_put_contents($source, '<?php $capability = "graphql.query";');
    file_put_contents($snapshot, '{}');

    try {
        expect(apiArchitectureViolations())->toContain('graphql-source: packages/operations/src/GraphqlProbe.php')
            ->toContain('graphql-snapshot: api/v2/graphql.json');
        expect(apiArchitectureViolations(composer: '{"require":{"webonyx/graphql-php":"^15"}}'))
            ->toContain('graphql-dependency: packages/operations/composer.json');
    } finally {
        unlink($source);
        unlink($snapshot);
    }
});

/** @return list<string> */
function apiArchitectureViolations(?string $composer = null): array
{
    $root = SourceScanner::repoRoot();
    $src = SourceScanner::packagePath('operations', 'src');
    $violations = [];
    $relative = static fn (string $path): string => substr($path, strlen($root) + 1);

    foreach (SourceScanner::filesMatching($src, '/\bclass\s+\w*(?:OperationRegistry|ApiRegistry)\b/') as $path) {
        if ($path !== $src.'/Definition/Internal/OperationRegistry.php') {
            $violations[] = 'second-operation-registry: '.$relative($path);
        }
    }

    foreach (SourceScanner::filesMatching($src, '/\bimplements\s+OperationInvoker\b/') as $path) {
        if (! in_array($path, [$src.'/Invocation/Internal/DefaultOperationInvoker.php', $src.'/Idempotency/Internal/IdempotentOperationInvoker.php'], true)) {
            $violations[] = 'unreviewed-operation-invoker: '.$relative($path);

            if (str_contains((string) file_get_contents($path), '->handle(')) {
                $violations[] = 'field-policy-bypass: '.$relative($path);
            }
        }
    }

    foreach (SourceScanner::filesMatching($src, '/(?:contractFingerprint\s*[:=]\s*)?hash\s*\([^;\n]*\$(?:tenant|account|workspace|principal|activation)\b/i') as $path) {
        $violations[] = 'tenant-profile-hash: '.$relative($path);
    }

    foreach (SourceScanner::filesMatching($src, '/->handle\s*\(/') as $path) {
        $name = basename($path);

        if ($name === 'HttpOperationDispatcher.php' || preg_match('/(?:Http|Tool)Adapter\.php$/', $name) === 1) {
            $violations[] = 'direct-adapter-handler: '.$relative($path);
        }
    }

    $controller = (string) file_get_contents($src.'/Internal/Http/ApiController.php');
    $dispatcher = (string) file_get_contents($src.'/Laravel/HttpOperationDispatcher.php');
    $tools = (string) file_get_contents(SourceScanner::packagePath('tools', 'src/Internal/GuardedToolInvoker.php'));
    $profile = (string) file_get_contents($src.'/Versioning/Data/ApiProfile.php');
    $factory = (string) file_get_contents($src.'/Versioning/Internal/ApiProfileFactory.php');
    $defaultInvoker = (string) file_get_contents($src.'/Invocation/Internal/DefaultOperationInvoker.php');
    $replay = (string) file_get_contents($src.'/Idempotency/Internal/DefaultReplayAuthorizer.php');
    $exposed = strstr($tools, "if (\$declaration->toolKind === 'exposed')");
    $exposed = $exposed === false ? '' : explode('} else {', $exposed, 2)[0];

    if (! str_contains($controller, '$this->dispatcher->dispatch(') || ! str_contains($dispatcher, '$this->invoker->invoke(')) {
        $violations[] = 'http-invoker-bypass';
    }

    if (! str_contains($exposed, '$this->operations->invoke(') || str_contains($exposed, '->handle(')) {
        $violations[] = 'exposed-tool-invoker-bypass';
    }

    if (! str_contains($profile, 'final readonly class ApiProfile') || preg_match('/function\s+(?:register|replace|freeze)\s*\(/i', $profile) === 1) {
        $violations[] = 'mutable-api-profile';
    }

    if (! str_contains($factory, 'CompiledRegistry $registry') || ! str_contains($factory, '$registry->api')) {
        $violations[] = 'profile-without-compiled-registry';
    }

    if (! str_contains($defaultInvoker, 'firstForbiddenWrite(') || ! str_contains($defaultInvoker, 'DeclaredFieldAuthorizer::prune(') || ! str_contains($replay, 'DeclaredFieldAuthorizer::prune(')) {
        $violations[] = 'field-policy-bypass';
    }

    foreach (['src', 'config', 'routes'] as $directory) {
        $path = SourceScanner::packagePath('operations', $directory);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (stripos($file->getFilename(), 'graphql') !== false || stripos((string) file_get_contents($file->getPathname()), 'graphql') !== false) {
                $violations[] = 'graphql-source: '.$relative($file->getPathname());
            }
        }
    }
    $composer ??= (string) file_get_contents(SourceScanner::packagePath('operations', 'composer.json'));

    if (stripos($composer, 'graphql') !== false || stripos((string) file_get_contents($root.'/composer.json'), 'graphql') !== false) {
        $violations[] = 'graphql-dependency: packages/operations/composer.json';
    }
    foreach (glob($root.'/api/v[12]/*') ?: [] as $path) {
        if (stripos(basename($path), 'graphql') !== false || (is_file($path) && stripos((string) file_get_contents($path), 'graphql') !== false)) {
            $violations[] = 'graphql-snapshot: '.$relative($path);
        }
    }

    sort($violations);

    return array_values(array_unique($violations));
}
