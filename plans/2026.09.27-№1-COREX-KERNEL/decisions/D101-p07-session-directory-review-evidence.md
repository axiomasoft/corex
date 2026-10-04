---
id: D101
date: 2026-10-04
status: accepted
item: P07.1
items: [P07, P07.1, P07.6]
supersedes: []
superseded_by: null
---
# D101 — p07-session-directory-review-evidence (accepted)

**Actor:** designer/codex/GPT-6 (exact runtime selector/effort unavailable), same owner-authorized P07 design repair.
**Evidence:** RAG:— installed FileSessionHandler::write/gc, artifacts/P07-design-remediation/file-session-directory.php and file-session-directory.json; artifacts/P07-grok-rereview/launch-ignored-window.json and route-evidence.json.

## Solution

1. Amend D100 session-file path: SessionConnectionBootstrapper injects Filesystem, creates tenant directory 0700 recursively before session read/gc/write and rejects unwritable paths. Revert restores handler/config without deleting stored sessions. P07.1 TenantSessionScopeTest must persist/read a real file and run GC; checking path alone is insufficient. Harness executes current Markdown bootstrapper plus actual FileSessionHandler.
2. Correct review-launch evidence: TOML table must be `[model."grok-4.7"]`, because unquoted `grok-4.7` parses as model key grok-4 with nested field 7 and CLI ignores it. First incomplete attempt was cancelled, retained separately, and provides no final review. Fresh independent Grok 4.7/high review uses the quoted table, configured context_window=500000, native supported windows and inspect with no model warning. This remains technical review, not formal design audit.

## Why

FileSessionHandler does not create its directory and may report true after a failed Filesystem::put; GC also fails on a missing directory. The previous review-launch example's TOML syntax did not actually set a 500k override. Both require actual runtime/effective-configuration evidence.

## Consequences

No product/DB change. Update P07.1 Files/Validation/BC and supporting carriers, rerun proofs and finish. Prior D100 and original review artifacts remain historical. P06 and actual frontier/xhigh design audit still gate product execution.
