# v2 compatibility — 2026-09-14

RAG:— repository carriers and discovered Task 0.16.1 marketplace runtime. Scope: qualification requested by `$ task:plan-design 2026.09.12-№1-COREX-SUCCESSOR,2026.09.12-№2-COREX-APPROVED-SPECS v2-compatibility`; no implementation, external operation, commit or Brain sync.

## Baseline and ownership

Installed runtime lint: predecessor 2 errors/3 warnings; this plan 3 errors/2 warnings. Predecessor errors belong to P2.1 write-site metadata and handoff title. This plan's errors are tool defects: Unicode № paths are ignored, bare D scanning misclassifies a named cross-plan filename as a local decision, and compact Context parsing rejects the reducer's canonical waiting-owner. Original reports were captured before mutation. Existing P2.1 bindings already reproduce all three runtime defects.

D5 qualifies tooling/navigation only. D1–D4, all 17 item specs, queue and existing product findings remain byte-identical. P2.1 remains Done; P2.2–P2.3 remain Not started and unqualified. Structural lint does not grant TEMPLATE-SCHEMA/TEMPLATE-SEAL, consumer/credential/source or irreversible authority.

## Compatible runtime profile

Patch: [v2-compatibility-runtime.patch](v2-compatibility-runtime.patch), SHA256 `2708b760d2c58f2e0f70105fa1ea536e966f8ef060c5dfa56c436405a37ed8fc`. Apply only to a disposable copy of the discovered `packages/task`; installed plugin/source stays unchanged. Reject an original file hash mismatch; do not force the patch or extend gates. After application require all compatible hashes below.

| Runtime file | Original SHA256 | Compatible SHA256 |
|:--|:--|:--|
| `lib/task/plan_weight.py` | `9307630cadbec7e6eee7150bdf168a1432c4a1159bfd3dc9de64579d68e09f48` | `359619e559d065c3806d6634afef06ed3cd307e6d06345dc6f6cc5a52b44b029` |
| `lib/task/plan_bundle.py` | `dd78998c69c1b6590b54f70298c59f1f75a5331b35b594949d99935d322a899d` | `52dd0cea2098193bccb6e96f071bd2786c4f10317a255bc0a71737d7fc801dcb` |
| `lib/task/plan_continuity.py` | `98c3bf1366584b2490e3a063df3206bdad24c259aa73501871f53c5c957ff654` | `7a5e87a59185c446c549b79b02d1f52c126d5be213e69c8c83943252494c7702` |

Reproduce using the marketplace's runtime root obtained from `codex plugin marketplace list --json`: copy its `packages/task` with `cp -a` to a new temporary directory; verify the original hashes; run `git apply --check` then `git apply` with this patch from the copied Task directory; verify compatible hashes. Run that copy's `scripts/plan-lint.py` and `scripts/plan-views.py` from repository cwd. The temporary directory is not a Required Read or portable execution input. A future execution runtime must incorporate these same verified semantics before dispatch; an installed version with equivalent behavior requires new file hashes and the same positive/negative checks, not an assumed version number.

Positive checks: literal cross-plan № path resolves to its original source; P2.2 bundle contains that source/full SHA; bare D1–D3 resolves local records; canonical waiting-owner parses as runnable=false. Negative checks: missing declared ASCII/№ files, missing bare D range and malformed Context/cold-start reason still fail. No change to schema/digest/authority/route/read admission predicates.

## Validation and delivery

Final measured checks and preservation hashes are in `v2-compatibility-validation.json`. Regenerate only affected state/status/decisions and eligible bundle, then check their owner projections. Future predecessor-dependent bundles stay deferred; no output or permission is fabricated. The optional index warning and truthful pending P2 clarification warning are retained.

One semantic Next: waiting for exact schema/seal/operator qualification plus compatible runtime → this plan P2.2–P2.3 via Routing.Exec=plan-exec. Native launch is guarded in handoff; the design root does not execute or spawn it. Installed runtime is still incompatible; isolated lint GREEN must always name the compatible profile.

Rollback: remove only new qualification evidence/decisions and restore changed handwritten navigation from baseline when explicitly abandoning this result. Never truncate appended journal rows; reversal of a durable qualification uses a new decision/observation and owner regeneration. Disposable runtime can be deleted. Product/schema/source/owner grants are unaffected.
