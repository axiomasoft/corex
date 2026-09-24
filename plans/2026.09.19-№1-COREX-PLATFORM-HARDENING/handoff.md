# HANDOFF — 2026-09-24 — after P11.4

**Next:** exec-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.5

| Параметр | Значение |
|:--|:--|
| Batch | E11d |
| Model | implementation |
| Thinking | high |
| Context | cold-start-root: whole-plan-independent-audit |
| Суть | P11.5 — versioned wakeup registry, stamped envelope, quarantine and read-only preflight. |

**Provider-native command:**

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.5'
```

**Sources of truth:** `phases/P11/P11.5.md` · `phases/P11/P11.4.md` ·
`decisions/D59-durable-work-pins-extension-version.md` ·
`decisions/D103-registry-fingerprint-is-provenance-only.md` · `journal.jsonl`.

**Done:** E11c GREEN. P11.4 stamps workflow trigger identity and version inside the
existing intent payload. The replay hash excludes `registryFingerprint`. Legacy
non-empty refs keep the `engine-binding/1` comparison and are not rewritten.
Workflow lint, analysis and tests passed (15/39).

**Remaining:** P11.5–P11.8, then P12–P17 according to the current status and
admission gates. Do not start P12.1 from this handoff.

**Open risks:** Consumer tests that register entities after the app is fully
booted will hit `RegistryFrozen`. Auth/CRM package lanes were not re-run in
E11c. D48 multi-account relay remains conditional. No P11.4 acceptance
deviation.

**Pointers:** `phases/P11/P11.5.md` · `findings/P11-receipts.md` ·
`packages/workflow/src/Internal/SqlWorkflowBinding.php` ·
`execution-commands.md` · `journal.jsonl`.
