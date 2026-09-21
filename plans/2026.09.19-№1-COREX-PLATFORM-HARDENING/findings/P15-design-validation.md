# P15 design validation — 2026-09-20

## Authoring and scope

GPT-5.6 Sol/high, provider-thread-state; exact route is in
`findings/P15-route-evidence.json`. Fourteen item contracts, normative design, D85–D90 and
bounded Perplexity evidence were authored. CoreX runtime files were not changed and no product
item was executed.

## Passed

- All P15.1–P15.14 bundles dry-render with the owning Task generator; none was persisted for
  future execution. Digest-bound results: `findings/P15-design-bundle-checks.json`.
- Maximum 7 Files and 14 Required Reads; every item stays within the declared 200K contract.
- Generated state (146 items), decision views (4), status and the current P1.1 bundle were rebuilt;
  all four `--check` commands passed.
- Final `plan-lint.py --gate all`: 0 errors, 0 warnings.
- `git diff --check -- <plan-dir>` passed.
- Both local Perplexity capture digests match `artifacts/P15-design-rag/manifest.json`;
  the timed-out Q0 is marked failure evidence, not a factual source.
- Journal JSONL parses completely; P15 authoring model appears in Meta, Update Log and journal.

## Repairs and limitations

1. P1 carried only one of four accepted A70 wakeup renames. D87 repairs the producer design and
   raises its registry count from 27 to 30.
2. The P15 skeleton incorrectly proposed `mod_*` migration and per-package changelogs.
   Repository evidence showed module ids are composer names; D86 forbids that DDL, and the
   repository-wide single root `CHANGELOG.md` rule is restored.
3. Q1/Q2 were contradictory across carriers. D89/D90 are proposed, not grants; only P15.11 and
   P15.12 are conditional. No-answer blocks those items, explicit reject closes them skipped.
4. Initial decision views rejected missing frontmatter/headings; bundle rendering rejected prose
   Inputs and ambiguous Required Reads; phase lint rejected a wrapped seam and invalid intent.
   Each owning carrier was repaired before final GREEN.
5. The normal command sandbox failed consistently with
   `bwrap: loopback: Failed RTM_NEWADDR: Operation not permitted`. The same `apply_patch` binary
   and validation commands were invoked through the available Node tool. This environment defect
   remains disclosed and was not treated as a passed shell check.
6. Product artifacts from P1/P6/P9/P10/P13 and all PHP/Composer package qualification remain
   future execution gates. Static bundle resolution proves design integrity, not product readiness.
7. Pre-existing uncommitted plan work was preserved; no commit was made.

## Next

P16 design in a fresh routed invocation (design-root-terminal). P16–P17 must finish before the
whole-plan Stage 2 audit; no execution is admitted.

