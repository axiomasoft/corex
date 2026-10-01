# ctx7-02 — Octane: время жизни биндингов, config repository и static state

- Tool: context7 `query-docs` · Library: `/laravel/docs` (13.x) · Date: 2026-10-01
- Query: "Octane dependency injection: container injection into singletons, config repository injection, scoped bindings reset between requests, static state memory leaks"
- Status: provenance.

## Answer (verbatim)

### Inject application container into singleton binding

Source: https://github.com/laravel/docs/blob/13.x/octane.md

Demonstrates an anti-pattern in Octane where injecting the application container into a singleton can cause the service to retain a stale container instance across subsequent requests.

```php
use App\Service;
use Illuminate\Contracts\Foundation\Application;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(Service::class, function (Application $app) {
        return new Service($app);
    });
}
```

--------------------------------

### Resolving configuration repository safely in service bindings

Source: https://github.com/laravel/docs/blob/13.x/octane.md

Shows workarounds for configuration injection in Octane by either avoiding singleton registration or injecting a resolver closure that fetches the latest config repository.

```php
use App\Service;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;

$this->app->bind(Service::class, function (Application $app) {
    return new Service($app->make('config'));
});

$this->app->singleton(Service::class, function () {
    return new Service(fn () => Container::getInstance()->make('config'));
});
```

--------------------------------

### Memory leak via static array accumulation

Source: https://github.com/laravel/docs/blob/13.x/octane.md

Illustrates how appending data to a static property on every request causes a memory leak because Octane retains application state in memory.

```php
use App\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handle an incoming request.
 */
public function index(Request $request): array
{
    Service::$data[] = Str::random(10);

    return [
        // ...
    ];
}
```

--------------------------------

### Register a scoped singleton binding

Source: https://github.com/laravel/docs/blob/13.x/container.md

Binds a class to resolve once within a single request or job lifecycle, flushing when a new lifecycle starts.

```php
use App\Services\Transistor;
use App\Services\PodcastParser;
use Illuminate\Contracts\Foundation\Application;

$this->app->scoped(Transistor::class, function (Application $app) {
    return new Transistor($app->make(PodcastParser::class));
});
```

### Binding > Binding Basics > Binding Scoped Singletons

Source: https://github.com/laravel/docs/blob/13.x/container.md

The `scoped` method binds a class or interface into the container that should only be resolved one time within a given Laravel request / job lifecycle. While this method is similar to the `singleton` method, instances registered using the `scoped` method will be flushed whenever the Laravel application starts a new "lifecycle", such as when a [Laravel Octane](/docs/{{version}}/octane) worker processes a new request or when a Laravel [queue worker](/docs/{{version}}/queues) processes a new job:
