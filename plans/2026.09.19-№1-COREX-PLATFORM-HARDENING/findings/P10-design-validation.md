# P10 design — validation evidence

**Date:** 2026-09-20  
**Actor:** plan-designer/codex-gpt-5.6-sol/high

## Green checks

- `plan-lint --gate all`: 0 errors, 0 warnings.
- Generated views: 83 item states, four decision views, status and P1.1 required
  bundle are fresh.
- Dry bundle render: P10.1–P10.8 all exit 0; bundles were not persisted.
- P10 RAG manifest: four SHA256 records match.
- Stable item schema: eight specs × eleven required fields.
- Product code diff under `packages/attributes`: none.

## Environment/tooling defect

`verify-plan-citations.py --strict` reported eight unresolved citations, all in
pre-P10 P2–P4/shape carriers. Inspection showed its `REPO_ROOT` is derived from the
owning swissknifeman Task checkout, while this selected plan lives in the separate
CoreX repository. It therefore searches `packages/attributes/**` under
swissknifeman and reports even the existing CoreX
`packages/attributes/src/AttributesServiceProvider.php` as missing.

This verifier result is **not green** and is not evidence against P10. No P10 carrier
introduced a line/symbol citation. Repair belongs to Task citation resolution: an
external-plan invocation must accept or derive the selected plan's repository root.
P10 does not patch that external package during a design-only run.
