# P16 design validation — 2026-09-20

## Authoring and scope

GPT-5.6 Sol/high, provider-thread-state; exact route is in
`findings/P16-route-evidence.json`. Thirteen item contracts, D91–D94 and bounded
Perplexity evidence were authored. CoreX runtime files were not changed and no product item was
executed.

## Passed

- All P16.1–P16.13 bundles dry-render with the owning Task generator; none was persisted for
  future execution. Digest-bound results: `findings/P16-design-bundle-checks.json`.
- Generated state (159 items), decision views (4), status and the current P1.1 bundle were rebuilt;
  all corresponding `--check` commands passed.
- Final `plan-lint.py --gate all`: 0 errors, 0 warnings.
- `git diff --check -- <plan-dir>` passed.
- Both local Perplexity capture digests match `artifacts/P16-design-rag/manifest.json`; the missing
  MoonShine URLs in the focused answer are recorded as a transport limitation, and official docs
  were checked separately.
- The P16 continuity decision and generated handoff delivery receipt validate against the owning
  Task runtime; journal JSONL and all new JSON carriers parse completely.

## Repairs and limitations

1. The P1 census was an initial-tree snapshot. Explicit producer files in P13/P14 raise API from
   55 to a floor of 155, so D91 makes API profile C; modules remains profile B at a floor of 99.
2. Structural work is physical only. D92 preserves post-P15 FQCNs through Composer PSR-4 prefix
   mappings and forbids a hidden second public namespace migration.
3. `corex/panels` stays named `panels`, remains profile A, and owns headless descriptors rather
   than an admin shell. This conclusion was checked against five official ecosystem vocabularies.
4. Testing carriers move to package-local `autoload-dev`; each package remains independently
   testable because dependency `autoload-dev` mappings do not propagate.
5. The first required route-capture command failed with `ModuleNotFoundError: core` because the
   Task adapter's `PYTHONPATH` omitted the marketplace root. The corrected command added both the
   marketplace root and Task lib, then produced the persisted provider-thread-state evidence. The
   initial failure was not counted as green.
6. Bundle rendering rejected directory-only reads, prose Inputs and compact file ranges. Each was
   replaced by exact file inputs or explicit brace expansion before the final GREEN.
7. Producer-adjusted counts remain design floors until execution. P16.13 must remeasure the live
   post-producer tree and fail closed on a profile mismatch.
8. Product qualification, Composer ambiguity negatives and package test lanes remain future
   execution gates. Static bundle resolution proves design integrity, not product readiness.
9. Pre-existing uncommitted plan work was preserved; no commit was made.

## Next

P17 design in a fresh routed invocation (design-root-terminal). P17 must finish before the
whole-plan Stage 2 audit; no execution is admitted.
