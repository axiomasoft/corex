# P14 design validation — 2026-09-20

## Authoring and scope

GPT-6 Astra/high, provider-thread-state; exact route is in P14-route-evidence.json.
21 item contracts, normative design, D74–D84, three Perplexity captures and five direct
primary-source retrievals. CoreX runtime files were not changed; no product item executed.

## Passed

- All P14.1–P14.21 bundles dry-render with the owning Task generator; none persisted
  for future execution. Digest-bound metrics: P14-design-bundle-checks.json.
- Maximum 8 Files, 10 Required Reads, 1990 selected input lines including conservative
  duplicate selections; <=57484 estimated input tokens versus 200K item ceiling.
- Generated state (132 plan items), decisions (4 views), status and current P1.1 bundle
  regenerated and `--check` passed. All P14 states remain Not started.
- All three raw Perplexity response SHA256 values match manifest; Q2 partial response
  marked partial and completed through focused Q3/direct sources.
- `git diff --check -- <plan-dir>` passed.
- Task route/config/adapter focused qualification: 55 route tests, 12 Codex adapter
  tests, 4 command catalog tests passed. Live adapter capture recognizes Astra/high;
  default renderer remains unambiguous Sol. Unknown/ambiguous inventory models rejected.

Final `plan-lint.py --gate all`: 0 errors, 0 warnings. Handoff delivery receipt
built and verified by `plan-delivery.py --kind handoff --verify`; exact Next is P15.
The terminal-only `--render-final` flag correctly refused handoff kind; used the
verified handoff receipt recommendation directly, without inventing a product terminal.

## Repairs and limitations

1. Initial Task route capture failed because gpt-6-astra was absent from its mapping.
   First candidate placed two selectors in frontier and broke default-command adapter
   qualification. Replaced by explicit-model classification through provider inventory;
   class_selectors retains only Sol. Fixed owning source in swissknifeman:
   command-routing.json, route_evidence_codex.py, test_route_evidence.py and root CHANGELOG.
   No blanket environment upgrade or cache projection edit; changes left uncommitted.
2. Installed cached adapter omits umbrella root in PYTHONPATH. Used existing corrected
   owning-source runtime with explicit root+task/lib; provider evidence was not fabricated.
3. Perplexity registered but not natively exposed. Registered stdio MCP succeeded.
   Generic web connector failed; direct primary HTTPS succeeded. OWASP HTML403 resolved
   via owning GitHub Markdown. Shopify overview now links to separate calculated-cost
   page; that exact primary page was read before confirming refund mechanics.
4. Early dry-bundle parse exposed prose in Inputs and a repeated path replacement in
   P14.8 Required Reads; repaired those carriers. Early plan-lint exposed misplaced
   Update Log heading and handoff field syntax; corrected to the owning v2 schema.
   These red intermediate checks are not final GREEN evidence.
5. P4/P5/P7/P11/P12/P13 product artifacts await stage3. Static bundle resolution is
   design integrity only. SQLite/PG/PHP/integration/security-review remain execution
   acceptance gates, not tests claimed to have run in this design invocation.
6. Pre-existing uncommitted P2–P13 work preserved; no commit of unrelated work.
   G-DDL/G-SECRET grants and live migration/rotation/external sends remain ungranted.

## Next

P15 design in a separate routed invocation (design-root-terminal); P15–P17 then stage2
whole-plan audit. Q1/Q2 remain scoped naming decisions for P15. No execution admitted.
