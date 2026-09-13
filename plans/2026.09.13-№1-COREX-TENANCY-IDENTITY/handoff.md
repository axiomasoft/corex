# HANDOFF — 2026-09-13 — after P3.1

**Next:** run-items: task:plan-run 2026.09.13-№1-COREX-TENANCY-IDENTITY P6.1

| Параметр | Значение |
|:--|:--|
| Batch | solo |
| Model | implementation · gpt-5.6-terra |
| Thinking | high |
| Capabilities | repository read/write, plan tools |
| Context | continue-root |
| Суть | Полная MCP ownership/readiness карта B33/B34; неизвестные handlers pending |

**Native Codex command:**

```bash
codex -C /home/vostrikov/projects/packages/corex -m gpt-5.6-terra -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-run 2026.09.13-№1-COREX-TENANCY-IDENTITY P6.1'
```

**Done:** P3.1 documents GREEN: exact proposed signup index/up-down SQL, candidate delta 0.2.3-signup-key-review.1 SHA-256 `385e4a16f54c28949775eaa1985c1585222e22ebc8f3f4dbf37391951af571f9`, approved base hashes, four JSON members, ascii-token-128-v1, retain-deny-v1, collision/backfill inventory method and isolated fixture rights request. Companion protocol reserves only qualified pool rows, binds status+key atomically, uses transaction-scoped lock/unique enforcement, recovers same accountId after uncertain commit and maps every fault/compensation boundary to P6.25. No schema/data/runtime/clone/DDL/private/live changes. P1 remains closed; P3 is not closed because P3.2 is unstarted and gated.

**Remaining:** P3.2 requires exact SIGNUP-DDL/fixture consent, PUBLIC-API major/CHANGELOG, named authorized P6.25 caller/digest/retention/compensation binding and current APPROVED-SPECS P2 integrity proof. Other D2 gates remain pending. P6.1 is separable documentation preparation with a fresh bundle. Its former prose input `available terminal package proof refs` was replaced with the concrete P1.2 proposal and explicit absence of runtime package proof; this is input qualification, not handler readiness. P6.1/P6.2 Routing was split into solo launches because installed lint requires a whole batch suffix while P6.2 has missing runtime proofs; model/effort and scope are preserved. No successor was executed.

**Sources of truth:** plan.md; phases/*/*.md; D1/D2; P1.1 inventory/bindings; P1.2 dossier; P3.1-signup-ddl.md and P3.1-replay-contract.md; qualified portable brief; journal.jsonl. Generated status/state/bundles are projections. Preserve the unrelated dirty baseline and foreign plans.

**Open risks:** Exact schema/major/caller/producer/fixture permissions; partial index permits tombstone reuse physically; retain-deny needs all-writer and purge-retention qualification. Existing pullPending commits without a key and can synchronously clone, so it must not precede keyed claim. Blueprint DROP/new-pool compensation requires central reconciliation with stable accountId; no automatic key release or resource DROP. Central trust/session/switcher/private/distribution/live gates remain independent. No current runner/pristine/runtime GREEN is inferred from these proposals.

**Verification:** V-DDL-DOSSIER exact SQL/delta/base/key/tombstone/rights self-check GREEN; V-REPLAY fault/owner/atomicity checks GREEN; V-PLAN scoped lint/views and724 protected source SHA-256 checks GREEN. Terminal-policy and task-contract write sites allow. SQL/delta/document digests are in the terminal journal receipt. Only documents and local plan bookkeeping changed; no DB/implementation tests or audit admission. Any relevant successor source delta needs requalification.
