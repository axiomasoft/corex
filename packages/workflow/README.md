# CoreX Workflow

`axiomasoft/corex-workflow` supplies durable, tenant-scoped intent capture and typed ports for an independently owned workflow engine adapter. It does not install an engine or schedule a worker itself.

## Installation

Install with `composer require axiomasoft/corex-workflow` from the approved CoreX channel. Install its package-owned schema on the intended tenant connection and bind an application-selected engine adapter before processing workflow intents. The application schedules its own engine worker.

## Configuration

`corex-workflow.enforce_declarations` defaults to true; setting it to false is a dangerous mode that production refuses unless `corex-workflow.enforce_declarations` is listed in `corex.acknowledged_dangerous_modes`. Supply explicit workflow bindings and definitions through module declarations and keep captured definition/version fingerprints with durable intents. The engine adapter and worker credentials/configuration remain application-owned.

## Failure semantics

Missing bindings, unavailable adapters and declaration/fingerprint mismatches are rejected before admitting an engine effect. A durable intent does not prove completion of an external engine action. Preserve its identity and reconcile unknown outcomes before retry.
