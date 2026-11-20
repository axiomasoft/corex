<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use Closure;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class SearchManager implements SearchDriverRegistry
{
    /** @var array<string,Closure(Application):CandidateDriver> */
    private array $candidates = [];

    /** @var array<string,Closure(Application):IndexDriver> */
    private array $indexes = [];

    public function register(string $name, Closure $factory): void
    {
        $this->candidates[$name] = $factory;
    }

    public function registerIndex(string $name, Closure $factory): void
    {
        $this->indexes[$name] = $factory;
    }

    public function candidate(Application $app, string $name): CandidateDriver
    {
        $factory = $this->candidates[$name] ?? throw new InvalidArgumentException('Unknown search candidate driver.');

        return $factory($app);
    }

    public function index(Application $app, string $name): IndexDriver
    {
        $factory = $this->indexes[$name] ?? throw new InvalidArgumentException('Unknown search index driver.');

        return $factory($app);
    }
}
