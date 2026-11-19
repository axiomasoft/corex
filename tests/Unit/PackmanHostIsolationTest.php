<?php

declare(strict_types=1);

use CoreX\Audit\Laravel\AuditRegistrar;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Foundation\Laravel\ConsoleRegistrar;
use CoreX\Foundation\Laravel\PackmanRegistrar;
use CoreX\Scheduler\SchedulerServiceProvider;
use Illuminate\Auth\GenericUser;
use Illuminate\Config\Repository;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Packman\Context\Contracts\ActorResolverInterface;

it('resolves actors for the owning host and current scope and keeps its Artisan commands local', function (): void {
    $host = static function (string $id): Application {
        $app = new Application;
        $app->instance('config', new Repository);
        $guard = Mockery::mock(Guard::class);
        $guard->allows('user')->andReturn(new GenericUser(['id' => $id]));
        $guard->allows('check')->andReturnTrue();
        $auth = Mockery::mock(Factory::class);
        $auth->allows('guard')->andReturn($guard);
        $app->instance(Factory::class, $auth);
        $app->instance(ImpersonationService::class, new NullImpersonationService);
        (new AuditRegistrar)->register($app);
        (new PackmanRegistrar)->register($app);

        return $app;
    };
    $commands = static fn (Application $app): Artisan => (new Artisan($app, $app->make(Dispatcher::class), $app->version()))->setContainerCommandLoader();

    $first = $host('alice');
    $first->register(SchedulerServiceProvider::class);
    (new ConsoleRegistrar)->boot($first);
    $actor = $first->make(ActorResolverInterface::class);
    expect($actor->current()->id)->toBe('alice');
    $second = $host('bob');
    expect($second->make(ActorResolverInterface::class)->current()->id)->toBe('bob')
        ->and($actor->current()->id)->toBe('alice')
        ->and($commands($second)->has('corex:audit:prune'))->toBeFalse()
        ->and($commands($first)->has('corex:audit:prune'))->toBeTrue()
        ->and($commands($second)->has('corex:scheduler:sweep'))->toBeFalse()
        ->and($commands($first)->has('corex:scheduler:sweep'))->toBeTrue();

    $first->forgetScopedInstances();
    $first->instance(Factory::class, $second->make(Factory::class));
    expect($first->make(ActorResolverInterface::class))->not->toBe($actor)
        ->and($actor->current()->id)->toBe('bob');
});
