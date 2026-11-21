# CoreX Tasks Workflow

`axiomasoft/corex-tasks-workflow` captures a durable Workflow intent when a task first completes.

## Installation

Install the package with Composer and enable its Laravel provider through package discovery or the application’s provider list.

## Configuration

Set `corex-tasks-workflow.enabled` to `true`, and set `corex-tasks-workflow.definition_ref` and `corex-tasks-workflow.version_ref` to immutable references supported by the selected Workflow engine. The bridge is disabled by default. Publish its configuration with `php artisan vendor:publish --tag=corex-tasks-workflow-config`.

## Host contracts

The host registers its Packman `ModuleActivationGate` policy and Workflow `EngineAdapter` in its Laravel provider. The engine resolves the exact pin from the `DefinitionRequest`, which identifies the current tenant context and the Tasks-owned `tsk.task/completed` trigger. Tasks supports one completion contributor when the bridge is enabled.

The host owns the gate's Laravel lifetime. `extend()` decorates a cached gate for that instance's lifetime, or registers a factory extender when the binding has no cached instance. To apply a policy in every scope, include it in the scoped factory or register the extender before the gate first resolves. Registry readers obtained after bridge registration follow the current gate chain across scope resets.

## Failure semantics

Enabled application boot checks the compiled trigger. Cold console bootstrap checks the installed Tasks declaration. Compile the Packman registry and activate Tasks for the tenant. Packman checks the host activation policy first and refuses activation if the trigger or pinned definition and version are unavailable for the tenant.

Tasks writes Done and its lifetime receipt in the transaction where Workflow captures the intent. First capture verifies that Tasks is active for the current tenant and resolves the pinned definition again so an unsupported configuration change closes capture for an already active tenant. Workflow processes committed intents through its usual executor. Reopening and completing a task again preserves the original intent and receipt.
