<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Events\TenantContextEnded;
use CoreX\Tenancy\Runtime\Events\TenantContextInitialized;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\ConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Runtime\Internal\TenantCleanupFailure;
use CoreX\Tenancy\Tests\Support\Engine\EngineRig;
use CoreX\Tenancy\Tests\Support\Engine\ScopedProbe;
use Psr\Log\LoggerInterface;

uses()->group('pg');

function workspaceTE(string $slug = 'w'): WorkspaceRef
{
    return new WorkspaceRef(id: '00000000-0000-4000-8000-0000000000'.strlen($slug).'1', slug: $slug, type: 'department', path: '/'.$slug, parentId: null);
}

it('initialize() bootstraps in order, end() reverts in reverse order, events fire once per switch', function (): void {
    $rig = new EngineRig;
    $seen = [];
    $rig->events->listen(TenantContextInitialized::class, function (TenantContextInitialized $e) use (&$seen): void {
        $seen[] = 'init:'.$e->accountId;
    });
    $rig->events->listen(TenantContextEnded::class, function (TenantContextEnded $e) use (&$seen): void {
        $seen[] = 'end:'.$e->accountId;
    });

    expect($rig->manager->initialized())->toBeFalse()->and($rig->manager->context())->toBeNull();

    $rig->manager->initialize($rig->ref('A'));
    $rig->manager->initialize($rig->ref('A')); // same account: no-op

    expect($rig->manager->context()->account->id)->toBe('A')
        ->and($rig->log->entries)->toBe(['bootstrap:one:A', 'bootstrap:two:A', 'bootstrap:three:A']);

    $rig->manager->initialize($rig->ref('B')); // another account ends the first one

    expect($rig->log->entries)->toBe([
        'bootstrap:one:A', 'bootstrap:two:A', 'bootstrap:three:A',
        'revert:three', 'revert:two', 'revert:one',
        'bootstrap:one:B', 'bootstrap:two:B', 'bootstrap:three:B',
    ]);

    $rig->manager->end();
    $rig->manager->end(); // idempotent

    expect($rig->manager->initialized())->toBeFalse()
        ->and($seen)->toBe(['init:A', 'end:A', 'init:B', 'end:B']);
});

it('setWorkspace() needs an account and workspace dies with the account', function (): void {
    $rig = new EngineRig;

    expect(fn () => $rig->manager->setWorkspace(workspaceTE()))->toThrow(TenantContextUnavailable::class);
    $rig->manager->setWorkspace(null); // clearing is always fine

    $rig->manager->initialize($rig->ref('A'));
    $rig->manager->setWorkspace(workspaceTE());
    expect($rig->manager->context()->workspace?->slug)->toBe('w');

    $rig->manager->initialize($rig->ref('B'));
    expect($rig->manager->context()->workspace)->toBeNull();
});

it('forgets scoped instances on every transition: central→A, A→B, A→central and leaving runFor', function (): void {
    $rig = new EngineRig;
    $central = $rig->probe();
    expect($rig->probe())->toBe($central);

    $rig->manager->initialize($rig->ref('A'));
    $inA = $rig->probe();
    expect($inA)->not->toBe($central)->and($rig->probe())->toBe($inA);

    $rig->manager->initialize($rig->ref('B'));
    $inB = $rig->probe();
    expect($inB)->not->toBe($inA);

    $rig->manager->end();
    $afterEnd = $rig->probe();
    expect($afterEnd)->not->toBe($inB);

    $inside = null;
    $rig->manager->runFor($rig->ref('A'), null, function () use ($rig, &$inside): void {
        $inside = $rig->probe();
    });
    expect($inside)->not->toBe($afterEnd)->and($rig->probe())->not->toBe($inside);
});

it('runFor() runs inside the context and restores the outer account and workspace, also when nested or failing', function (): void {
    $rig = new EngineRig;
    $outer = workspaceTE('outer');
    $rig->manager->initialize($rig->ref('A'));
    $rig->manager->setWorkspace($outer);

    $result = $rig->manager->runFor($rig->ref('B'), workspaceTE('inner'), function () use ($rig): string {
        expect($rig->manager->context()->account->id)->toBe('B')
            ->and($rig->manager->context()->workspace?->slug)->toBe('inner');

        return 'done';
    });

    expect($result)->toBe('done')
        ->and($rig->manager->context()->account->id)->toBe('A')
        ->and($rig->manager->context()->workspace)->toBe($outer);

    expect(fn () => $rig->manager->runFor($rig->ref('B'), null, static fn () => throw new LogicException('boom')))
        ->toThrow(LogicException::class, 'boom');
    expect($rig->manager->context()->account->id)->toBe('A')->and($rig->manager->context()->workspace)->toBe($outer);

    $rig->manager->end();
    $rig->manager->runFor($rig->ref('A'), null, static fn () => null);
    expect($rig->manager->initialized())->toBeFalse();
});

it('a throwing bootstrapper reverts itself and every predecessor in reverse order and leaves central', function (): void {
    $rig = new EngineRig;
    $rig->bootstrappers[1]->failBootstrap = new RuntimeException('two failed');
    $before = $rig->probe();

    expect(fn () => $rig->manager->initialize($rig->ref('A')))->toThrow(RuntimeException::class, 'two failed');

    expect($rig->log->entries)->toBe(['bootstrap:one:A', 'bootstrap:two:A', 'revert:two', 'revert:one'])
        ->and($rig->manager->initialized())->toBeFalse()
        ->and($rig->manager->context())->toBeNull()
        ->and($rig->probe())->not->toBe($before);

    // the engine stays usable: nothing was poisoned
    $rig->bootstrappers[1]->failBootstrap = null;
    $rig->manager->initialize($rig->ref('A'));
    expect($rig->manager->initialized())->toBeTrue();
});

it('a missing account, a failing resolver and a failing listener restore the caller account and workspace and never run the callback', function (Closure $break): void {
    $rig = new EngineRig;
    $outer = workspaceTE('outer');
    $rig->manager->initialize($rig->ref('A'));
    $rig->manager->setWorkspace($outer);
    $break($rig);
    $ran = false;

    expect(fn () => $rig->manager->runFor($rig->ref('B'), null, function () use (&$ran): void {
        $ran = true;
    }))->toThrow(Exception::class);

    expect($ran)->toBeFalse()
        ->and($rig->manager->context()->account->id)->toBe('A')
        ->and($rig->manager->context()->workspace?->slug)->toBe('outer');
})->with([
    'missing account' => [static function (EngineRig $rig): void {
        unset($rig->accounts->statuses['B']);
    }],
    'resolver failure' => [static function (EngineRig $rig): void {
        // A is already initialized; only B's resolution fails
        $rig->connections->failures['B'] = new RuntimeException('resolver down');
    }],
    'initialized listener failure' => [static function (EngineRig $rig): void {
        $rig->events->listen(TenantContextInitialized::class, static function (TenantContextInitialized $e): void {
            if ($e->accountId === 'B') {
                throw new RuntimeException('listener down');
            }
        });
    }],
]);

it('a failing revert still runs the remaining reverts, poisons the runtime and refuses every further call', function (): void {
    $rig = new EngineRig;
    $rig->manager->initialize($rig->ref('A'));
    $rig->bootstrappers[1]->failRevert = new RuntimeException('two cannot revert');
    $before = $rig->probe();

    expect(fn () => $rig->manager->end())->toThrow(RuntimeException::class, 'two cannot revert');
    expect(array_slice($rig->log->entries, -3))->toBe(['revert:three', 'revert:two', 'revert:one'])
        ->and($rig->probe())->not->toBe($before);

    foreach ([
        'context' => static fn (EngineRig $r) => $r->manager->context(),
        'initialized' => static fn (EngineRig $r) => $r->manager->initialized(),
        'initialize' => static fn (EngineRig $r) => $r->manager->initialize($r->ref('B')),
        'end' => static fn (EngineRig $r) => $r->manager->end(),
        'setWorkspace' => static fn (EngineRig $r) => $r->manager->setWorkspace(null),
    ] as $call) {
        expect(fn () => $call($rig))->toThrow(TenantContextUnavailable::class, 'must be recreated');
    }
});

it('runFor() on a poisoned runtime keeps the original failure instead of replacing it', function (): void {
    $rig = new EngineRig;
    $rig->manager->initialize($rig->ref('A'));
    $rig->bootstrappers[2]->failRevert = new RuntimeException('three cannot revert');

    // the callback switches accounts; the revert of A fails and poisons before the callback ever runs
    expect(fn () => $rig->manager->runFor($rig->ref('B'), null, static fn () => 'unreachable'))
        ->toThrow(RuntimeException::class, 'three cannot revert');
    expect(fn () => $rig->manager->context())->toThrow(TenantContextUnavailable::class);
});

it('a bootstrap failure plus a failing revert keeps both causes, logs both and survives a dead log transport', function (bool $logDown): void {
    $rig = new EngineRig;
    $rig->logger->failing = $logDown;
    $original = new RuntimeException('bootstrap failed');
    $cleanup = new RuntimeException('revert failed');
    $rig->bootstrappers[1]->failBootstrap = $original;
    $rig->bootstrappers[0]->failRevert = $cleanup;

    try {
        $rig->manager->initialize($rig->ref('A'));
        $this->fail('The double failure was swallowed.');
    } catch (TenantCleanupFailure $failure) {
        expect($failure->getPrevious())->toBe($original)->and($failure->cleanup)->toBe($cleanup);
    }

    if ($logDown) {
        expect($rig->logger->records)->toBe([]);
    } else {
        expect($rig->logger->records)->toHaveCount(1)
            ->and($rig->logger->records[0]['context']['exception'])->toBe($cleanup)
            ->and($rig->logger->records[0]['context']['original_exception'])->toBe($original);
    }

    expect(fn () => $rig->manager->initialized())->toThrow(TenantContextUnavailable::class);
})->with([false, true]);

it('a failing callback plus a failing restoration keeps both causes, for a central and for an initialized outer account', function (bool $withOuter): void {
    $rig = new EngineRig;

    if ($withOuter) {
        $rig->manager->initialize($rig->ref('A'));
    }
    $callbackFailure = new LogicException('callback failed');
    $restoreFailure = new RuntimeException('restore failed');

    try {
        $rig->manager->runFor($rig->ref('B'), null, function () use ($rig, $callbackFailure, $restoreFailure): never {
            // restoring the caller context has to revert B's bootstrappers
            $rig->bootstrappers[0]->failRevert = $restoreFailure;

            throw $callbackFailure;
        });
        $this->fail('The double failure was swallowed.');
    } catch (TenantCleanupFailure $failure) {
        expect($failure->getPrevious())->toBe($callbackFailure)->and($failure->cleanup)->toBe($restoreFailure);
    }

    expect($rig->logger->records)->toHaveCount(1);
})->with([false, true]);

it('boots on the real container: eager bootstrapper graph is acyclic and never reaches the manager', function (): void {
    $manager = app(TenancyManager::class);

    expect($manager)->toBeInstanceOf(DatabaseTenancyManager::class);

    foreach (app(TenancyOptions::class)->bootstrappers as $class) {
        $constructor = (new ReflectionClass($class))->getConstructor();
        $types = array_map(
            static fn (ReflectionParameter $p): string => (string) $p->getType(),
            $constructor?->getParameters() ?? [],
        );

        expect($types)->not->toContain(TenancyManager::class)
            ->and($types)->not->toContain(TenantContextResolver::class)
            ->and($types)->not->toContain(DatabaseTenancyManager::class);
    }
});

it('binds the PSR logger into the engine (provider boot)', function (): void {
    $manager = app(TenancyManager::class);
    $logger = (new ReflectionProperty($manager, 'logger'))->getValue($manager);

    expect($logger)->toBe(app(LoggerInterface::class));
});

it('refuses an unsafe facility configuration before the first switch', function (Closure $configure, string $violation): void {
    $configure();
    app()->forgetInstance(TenancyOptions::class);
    app()->forgetInstance(DatabaseTenancyManager::class);

    try {
        app(TenancyManager::class);
        $this->fail('The unsafe configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect(implode("\n", $exception->violations))->toContain($violation);
    }
})->with([
    'array tenant cache' => [static function (): void {
        config(['cache.stores.array_probe' => ['driver' => 'array'], 'corex-tenancy.cache_stores' => ['array_probe']]);
    }, 'corex-tenancy.cache_stores.array_probe: unsupported tenant cache driver'],
    'unconfigured tenant store' => [static function (): void {
        config(['corex-tenancy.cache_stores' => ['ghost']]);
    }, 'corex-tenancy.cache_stores.ghost: store is not configured'],
    'relative file store' => [static function (): void {
        config(['cache.stores.tenant_file.path' => 'relative/path']);
    }, 'file store needs an absolute path'],
    'unsupported session driver' => [static function (): void {
        config(['session.driver' => 'array']);
    }, 'corex-tenancy.session: unsupported session driver'],
    'relative file sessions' => [static function (): void {
        config(['session.files' => 'relative']);
    }, 'corex-tenancy.session.files: file sessions need an absolute directory'],
    'redis session without a dedicated store' => [static function (): void {
        config(['session.driver' => 'redis', 'session.store' => null]);
    }, 'corex-tenancy.session.store: a dedicated configured cache store is required'],
    'redis session store of the wrong driver' => [static function (): void {
        config(['session.driver' => 'redis', 'session.store' => 'tenant_file', 'session.connection' => 'default']);
    }, 'must use the redis driver'],
    'session store shared with the tenant stores' => [static function (): void {
        config([
            'cache.stores.shared' => ['driver' => 'redis', 'connection' => 'cache'],
            'corex-tenancy.cache_stores' => ['shared'],
            'session.driver' => 'redis',
            'session.store' => 'shared',
            'session.connection' => 'default',
        ]);
    }, 'must be separate from the tenant cache and host stores'],
    'host store that is a tenant store' => [static function (): void {
        config([
            'corex-tenancy.host_identification.enabled' => true,
            'corex-tenancy.host_identification.cache_store' => null,
            'cache.default' => 'tenant_file',
        ]);
    }, 'the host store must not be a per-account store'],
]);

it('accepts the supported tenant cache drivers', function (string $driver, array $store): void {
    config(['cache.stores.drv_probe' => $store + ['driver' => $driver], 'corex-tenancy.cache_stores' => ['drv_probe']]);
    app()->forgetInstance(TenancyOptions::class);
    app()->forgetInstance(DatabaseTenancyManager::class);

    expect(app(TenancyManager::class))->toBeInstanceOf(DatabaseTenancyManager::class);
})->with([
    'redis' => ['redis', ['connection' => 'cache']],
    'memcached' => ['memcached', ['servers' => [['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]]]],
    'database' => ['database', ['table' => 'cache']],
    'file' => ['file', ['path' => '/tmp/corex-probe']],
]);

it('keeps the TenantBootstrapper contract open for host bootstrappers after the stock ones', function (): void {
    $host = new class implements TenantBootstrapper
    {
        public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void {}

        public function revert(): void {}
    };

    expect($host)->toBeInstanceOf(TenantBootstrapper::class)
        ->and([ConnectionBootstrapper::class, CachePrefixBootstrapper::class, SessionConnectionBootstrapper::class])
        ->each->toImplement(TenantBootstrapper::class);
});

it('the scoped probe really is rebuilt per generation (harness self-check)', function (): void {
    $rig = new EngineRig;
    $first = $rig->probe();
    $rig->manager->initialize($rig->ref('A'));

    expect($rig->probe()->generation)->toBeGreaterThan($first->generation)
        ->and(ScopedProbe::$built)->toBe(2);
});
