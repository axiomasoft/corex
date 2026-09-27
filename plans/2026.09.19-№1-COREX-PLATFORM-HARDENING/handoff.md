# HANDOFF — 2026-09-27 — after P17.6

**Next:** task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.7

| Batch | Model | Thinking | Context | Суть | Команда |
|:--|:--|:--|:--|:--|:--|
| E17c | grok-4.7 | high | continue-root | Supported-version upgrade proof for the frozen candidate. P17.6 is closed. | task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.7 |

```session-continuity-decision/v1
{"outcome":"continue-root","reason":"authorized-scope-complete","evidence":["lifecycle:scope-complete","persisted-context:continue-root","checkpoint:P17.6:sha256:9f0af7384d06db47a463cd64196324371450c91531e1aed66f7cfe3f2dc62a10"],"runnable":true,"schema_version":"session-continuity-decision/v1"}
```

**Done:** P17.6 is GREEN. Six installation profiles and the declared PHP/Laravel edges boot from the frozen dist candidate. Meilisearch stays absent, Search Intl can be removed and the application boots again, and every cohort package has min, max, and prefer-lowest coverage. Laravel 13 is excluded for `corex/access`; PHP 8.3 is excluded for `corex/tenancy`.

**Remaining:** P17.7–P17.12 remain. P17.7 is the next plan item. The committed webhook topic list is still empty.

**Open risks:** The candidate is local and experimental. Upgrade, DoD, stable-metadata, sealed-packet, and publication proofs remain. P17.12 still requires exact G-RELEASE authority. Owner waived executor verification for this run because the launch argv had no model and cli-config had no reasoning_effort; the recorded route is grok-4.7/high via cursor-explicit-attestation-v1.

**Sources of truth:** `findings/P17-profile-receipt.json`; `artifacts/P17/profile-matrix.json`; `findings/P17-consumer-receipt.json`; `findings/P17-candidate-receipt.json`; append-only `journal.jsonl`.
