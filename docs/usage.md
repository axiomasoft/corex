# Usage

This page shows the integration calls most hosts need. The complete supported
surface is generated in [public API](reference/public-api.md); each package
README documents its own commands, events and extension points.

## Rules of the public surface

- Call or catch only symbols marked `#[Api]`.
- Implement only interfaces marked `#[Spi(kind: SpiKind::Implement)]`.
- Never import `#[Internal]` classes or anything under an `Internal`
  namespace of another package.
- Commands take immutable data objects and return references or views; they
  never return Eloquent models.

## Bind the host authorizers

Modules fail closed: until the host binds an authorizer, the default
implementation throws `AuthorizationException` for every write. For Tasks:

```php
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;

$this->app->bind(TaskAccess::class, MyTaskAccess::class);
$this->app->bind(TaskMutationAccess::class, MyTaskMutationAccess::class);
```

`axiomasoft/corex-access` provides the role matrix, direct grants and record
visibility; an authorizer usually delegates to its `AccessChecker`:

```php
use CoreX\Access\Contracts\AccessChecker;

if (! $access->allowed($principal, 'tsk.task', 'create')) {
    throw new AuthorizationException();
}
```

`CoreX\Access\Laravel\ScopeResolver::apply()` restricts an Eloquent query to
the records the principal may see.

## Run work in a scope

```php
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;

$context = $resolver->current();
$scope = $scopes->forCurrentPrincipal($context);

$runner->run($scope, fn () => $handler->handle($input));
```

Queued jobs and console commands carry the `DataScope` and run their work the
same way; a scope from a superseded database binding throws
`CoreX\Context\Exceptions\ScopeStale`.

## Call a module command

```php
use CoreX\Audit\Enums\ActorType;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\TaskDraft;

$task = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
    workspaceId: $workspaceId,
    title: 'Prepare the offer',
    actor: new ActorData(ActorType::User, $userId),
));

$task->id; // TaskReference
```

Validation failures throw `ValidationException`; domain failures throw typed
exceptions that carry an error descriptor (see [Errors](errors.md)).

## Extend a module

Extension points are registries and SPI interfaces declared by each package,
for example search providers, custom field types, webhook topics or tool
schemas. The rules for registering an extension and its lifecycle are in the
[extension policy](architecture/extension-policy.md).
