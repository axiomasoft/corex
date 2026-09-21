# P17 design validation — 2026-09-20

## Authoring and scope

GPT-5.6 Sol/high, provider-thread-state; exact route is in `findings/P17-route-evidence.json`.
Twelve item contracts, D95–D97 and one bounded Perplexity query were authored. CoreX runtime files,
release metadata, remote refs and registries were not changed; no product/release item was executed.

## Passed

- All P17.1–P17.12 bundles dry-render with the owning Task generator; none was persisted for future
  execution. Digest-bound results are in `findings/P17-design-bundle-checks.json`.
- Generated state (171 items), decision views (4), status and the current P1.1 bundle were rebuilt;
  all corresponding structural checks passed.
- `plan-lint.py --gate all`: 0 errors, 0 warnings.
- `plan-weight.py item` passed admission, D2a and D2b for all twelve P17 items with no gaps; the
  maximum estimated item weight is 69,506 tokens (P17.9) against the 100,000-token item contract.
- `git diff --check -- <plan-dir>` passed.
- `plan-delivery.py` generated and verified the P17 handoff receipt for Stage 2.
- P17 Perplexity capture/manifest digests match, and all five primary official URLs returned HTTP
  200. No obsolete Laravel 5.7 result was used.
- JSON carriers parse, DOD IDs in the design are exactly 27, and P17 has exactly 12 item/state pairs.

## Repairs and limitations

1. A111 and the original P17 skeleton called the source list “27 conditions”, but the pinned audit
   contains 25 bullets. D95 makes provenance explicit and adds only the two already normative plan
   promises: pristine split-profile installation and an independent non-CRM/CMS/Store consumer.
2. Package identities are intentionally not hardcoded before D89/D90 execution. P17.1 derives and
   freezes the cohort; omission, unknown channel and dependency gap are release failures.
3. P17.12 is the only live-write item. It is `manual`, requires packet-bound G-RELEASE and cannot
   force, delete, rewrite, create a repository, change visibility or provision secrets.
4. Upgrade baselines remain external/live facts until P17.7. The design requires immutable
   per-package evidence and forbids substituting a monorepo tag.
5. The first required route-capture command failed with `ModuleNotFoundError: core` because the Task
   adapter's `PYTHONPATH` omitted the marketplace root. The corrected invocation added the root and
   produced provider-thread-state evidence; the initial failure is not counted green.
6. The generic web search connector returned no usable payload and then a connection failure.
   Perplexity MCP succeeded; official URLs were checked directly, and the transport defect is in the
   RAG manifest.
7. Pre-existing uncommitted plan work was preserved; no commit was made.

## Next

Stage 2 whole-plan independent design audit in a fresh GPT-6 Astra/high root. Design-root-terminal:
this invocation does not start the audit or any P17 item.
