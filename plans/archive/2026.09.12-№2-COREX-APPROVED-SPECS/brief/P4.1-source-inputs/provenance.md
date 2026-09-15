# P4.1 — Portable original package-lock input

The original `packages/tenancy/composer.lock` is intentionally Git-ignored by the repository.
Execution-input ancestor verification reproduced `EXECUTION_INPUTS_NOT_ANCESTOR` for that
path. Copy its exact bytes to `tenancy-composer.lock` in this brief directory, instead of
changing ignore rules, tracking the live package lock, or inventing replacement evidence.

Original/snapshot SHA256:
`518ee0151af7d47d4711534a293dafb181f25faa9f364003e6661dd3dc50424a`.
This matches the original P4.1 bundle and D2 observation. The P4.1 builder checks the
unchanged live original at its boundary. The copied source data is inert input, not a
new dependency resolution or adoption artifact.

Actor: plan-run/frontier/high · gpt-5.6-sol, 2026-09-14.
Authority: current exact P4.1 invocation and D9's routine technical delegation.
