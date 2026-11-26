# CoreX Workflow

`axiomasoft/corex-workflow` supplies durable, tenant-scoped intent capture and typed ports for an independently owned workflow engine adapter. It does not install an engine or schedule a worker itself.

## Installation

Install with `composer require axiomasoft/corex-workflow` from the approved CoreX channel. Install its package-owned schema on the intended tenant connection and bind an application-selected engine adapter before processing workflow intents. The application schedules its own engine worker.

## Public API

Use `WorkflowBinding::capture()` within the verified owner transaction to admit a durable intent. Use `WorkflowBinding::transition()` for a synchronous transition delegated to the installed `EngineAdapter::transition()`.

The host implements `EngineAdapter::provider(DefinitionRequest)` to resolve the pinned definition and version in the trusted tenant context without external I/O. Activation and durable execution use the same definition lookup.

## Configuration

`corex-workflow.enforce_declarations` defaults to true; setting it to false is a dangerous mode that production refuses unless `corex-workflow.enforce_declarations` is listed in `corex.acknowledged_dangerous_modes`. Supply explicit workflow bindings and definitions through module declarations and keep captured definition/version fingerprints with durable intents. The engine adapter and worker credentials/configuration remain application-owned.

## Failure semantics

Missing bindings, unavailable adapters and declaration/fingerprint mismatches are rejected before admitting an engine effect. A durable intent does not prove completion of an external engine action. Preserve its identity and reconcile unknown outcomes before retry.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Fresh installations use `axiomasoft/corex-workflow:tenant` v2.
