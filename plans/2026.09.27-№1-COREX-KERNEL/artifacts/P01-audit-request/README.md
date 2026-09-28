# P01 independent phase audit — launch preparation

Owner request: «посмотри в плане, после выполнения фазы нужен аудит, вроде на grok, надо подготовить команду».
Prepared only; no audit or owner grant has been recorded or consumed. Launching the command below supplies a NEW explicit owner prompt for one candidate-bound audit.

Sources: phases/P01/P01.md:28 (`Audit Intent: auto`, advisory under current Task); roadmap.md execution-sheet (item reviewer Cursor/grok-4.7/high); session-routing.md pool; installed Task `configs/command-routing.json` (phase audit frontier/xhigh); canonical `audit_admission.py` (single-use owner-bound admission). Actual local `agent models` lists grok-4.7-xhigh.

Question and scope: admission-proposal.json. Material candidate: candidate.json, fingerprint `22f49c2cab5a7e268f88bb22d250d67af6b1225a0f9a4fbc300655469346a0cb`. These are preparation artifacts, not an accepted decision or runnable audit admission. The actual owner-launched prompt must be recorded before journal start; the auditor creates the accepted D# only on that new invocation and checks candidate identity/admission.

Native Cursor command:

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-xhigh --force --sandbox disabled --trust --approve-mcps "$(cat /home/vostrikov/projects/packages/corex/plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01-audit-request/launch.txt)"
```

The command pins the CoreX repository root and uses an absolute prompt path. Product is read-only; audit-owned bookkeeping may change. Reuse GREEN unchanged item evidence; record any product findings against their owning item. O-3 remains required before P02; this local audit does not prove live CI.
