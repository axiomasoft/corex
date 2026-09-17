# P5.1 upstream-source qualification evidence

**Environment:** clean temporary directories outside the CoreX checkout,
PHP 8.4.1, Composer; no `corex/*` dependency was installed.

## Symfony Workflow FSM

The immutable `symfony/workflow` 7.4.9 split source was installed with Composer.
The package's canonical tests live in Symfony's monorepo, so the test carrier
used the same component at canonical tag `v7.4.9`:

```text
./phpunit src/Symfony/Component/Workflow
OK (218 tests, 579 assertions)
```

Actual adapter map frozen from this source:

- `Symfony\Component\Workflow\WorkflowInterface` — transition application;
- `Symfony\Component\Workflow\Definition` — immutable definition input;
- `Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface` — state
  persistence seam;
- component events are observer-only; transaction, tenant context, request-key
  deduplication and due-source ownership stay in the CoreX adapter.

## Durable Workflow engine

The immutable `durable-workflow/sdk` 2.0.10 source was installed in a separate
clean directory and its upstream suite was executed:

```text
composer test
OK, but some tests were skipped!
Tests: 639, Assertions: 3673, Skipped: 1.
```

Actual adapter map frozen from this source:

- `DurableWorkflow\Client::startWorkflow()` and
  `DurableWorkflow\WorkflowHandle::signal()` — start/signal operation boundary;
- `DurableWorkflow\Worker::create(...)->run(int $maxPolls)` — bounded worker
  polling; CoreX's runner calls a bounded poll, never a separate scheduler;
- `DurableWorkflow\ScheduleHandle` exists but is not registered by the binding
  profile; CoreX `DueSource` remains the authoritative timer source;
- upstream tests cover signal declaration/transport, timers, retries, schedules
  and replay regression fixtures. Their proof does not substitute CoreX's own
  tenant transaction, authorization, idempotency or DueSource conformance tests.
