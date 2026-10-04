<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;

/** A fully wired engine over fakes, with everything a test wants to look at. */
final class EngineRig
{
    public EngineLog $log;

    public Container $container;

    public Dispatcher $events;

    public FixedAccounts $accounts;

    public FixedConnections $connections;

    public RecordingLogger $logger;

    /** @var list<SpyBootstrapper> */
    public array $bootstrappers;

    public DatabaseTenancyManager $manager;

    /** @param list<string> $names */
    public function __construct(array $names = ['one', 'two', 'three'], string $central = 'central')
    {
        ScopedProbe::$built = 0;
        $this->log = new EngineLog;
        $this->container = new Container;
        $this->container->scoped(ScopedProbe::class);
        $this->events = new Dispatcher($this->container);
        $this->accounts = new FixedAccounts(['A' => 'active', 'B' => 'active']);
        $this->connections = new FixedConnections;
        $this->logger = new RecordingLogger;
        $this->bootstrappers = array_map(fn (string $name): SpyBootstrapper => new SpyBootstrapper($name, $this->log), $names);
        $this->manager = new DatabaseTenancyManager(
            bootstrappers: $this->bootstrappers,
            accounts: $this->accounts,
            connections: $this->connections,
            container: $this->container,
            events: $this->events,
            centralConnection: $central,
            logger: $this->logger,
        );
    }

    public function ref(string $id): AccountRef
    {
        return new AccountRef(id: $id, slug: 'acc-'.$id, status: 'active', features: [], limits: []);
    }

    public function probe(): ScopedProbe
    {
        return $this->container->make(ScopedProbe::class);
    }
}
