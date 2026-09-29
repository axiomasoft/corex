<?php

declare(strict_types=1);

use CoreX\Access\Matrix\ScopeMapVersion;
use CoreX\Access\Support\AuthOptions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\SingleAccountResolver;
use Illuminate\Contracts\Cache\Factory;
use Symfony\Component\Process\Process;

it('invalidates the captured tenant even when the active context changes before commit', function (): void {
    $resolver = Mockery::mock(TenantContextResolver::class);
    $context = (new SingleAccountResolver)->current();
    $original = $context;
    $resolver->shouldReceive('current')->andReturnUsing(function () use (&$context) {
        return $context;
    });
    $versions = new ScopeMapVersion($resolver, app(Factory::class), app(AuthOptions::class));
    $invalidate = $versions->invalidator('captured-role');
    $context = new TenantContext(new AccountRef('another-account', 'other', 'active', [], []), null);
    $invalidate();
    expect($versions->current('captured-role'))->toBe(0);
    $context = $original;
    expect($versions->current('captured-role'))->toBe(1);
});

it('retains every concurrent invalidation even on the non-atomic file cache', function (): void {
    $path = database_path('version-cache');
    config(['cache.stores.remediation' => ['driver' => 'file', 'path' => $path], 'corex-access.cache.store' => 'remediation']);
    $processes = [];
    for ($worker = 0; $worker < 4; $worker++) {
        $process = new Process([PHP_BINARY, __DIR__.'/../Fixtures/BumpScopeVersion.php', $path]);
        $process->start();
        $processes[] = $process;
    }
    foreach ($processes as $process) {
        expect($process->wait())->toBe(0, $process->getErrorOutput());
    }
    $remediationOptions = AuthOptions::fromArray(['cache' => ['store' => 'remediation']]);
    expect((new ScopeMapVersion(new SingleAccountResolver, app(Factory::class), $remediationOptions))->current('concurrent-role'))->toBe(400);
});
