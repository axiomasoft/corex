# Release evidence

Frozen inputs and receipts of the manual release tooling
(`scripts/dod-1.0.py`, `scripts/distribution/*`, workflows
`distribution-proof.yml` and `remaining-cohort-proof.yml`). Plans under
`plans/` are working material and may be deleted; nothing here may point back
into `plans/`.

| Directory | Contents | Read/written by |
|:--|:--|:--|
| `1.0/` | CoreX 1.0 candidate: `artifacts/P17/release-cohort.json` (frozen candidate), `artifacts/P17/profile-matrix.json`, `findings/P17-*.json` receipts, DOD-17 receipt (`knowledge/`, `decisions/D107.json`), DOD-01 freshness input (`scripts/architecture/src/Scorecard.php`), `owners.json` (frozen DoD owner index) | `dod-1.0.py`, `release_preflight.py`, `upgrade-proof.py`, `profile-proof.py`, `verify_*.py` |
| `remaining-cohort-20260915/` | Remaining-cohort (flex-crm) release record: P1–P8 producer seals and P2 contracts read by intake, `findings/P9-*.md` receipts, `research/P9-cohort-design.md` (design contract), `decisions/D*.md` (owner authority, e.g. D14), `delivery/remaining-cohort-20260915/evidence/` | `remaining-cohort-*.py` |

The relative layout inside each directory mirrors the plan it was frozen from
(`1.0/` ← `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING`,
`remaining-cohort-20260915/` ← `plans/archive/2026.09.15-№1-COREX-FLEXCRM-REMAINING`), so receipt-internal
relative paths and digests stay valid. Only the files the scripts read or write were copied, byte-identically, on
2026-10-08 (audit 2026-10-08, P-12). `owners.json` replaces resolving DoD
`owner` ids against plan phase specs: it records each phase id, its title and
the spec digest at freeze time.

Receipts are evidence of a past run, not live state: regenerate them with the
producing script (which now writes here by default) rather than editing them.
Build products under the git-ignored `/delivery/` are unaffected.
`scripts/distribution/*.py` and `scripts/dod-1.0.py` do not run in CI gates;
they are manual release steps.

Not covered: `scripts/distribution/publish_exact.py` is a one-off `task:plan-run`
executor for P5.3 of `plans/archive/2026.09.12-№2-COREX-APPROVED-SPECS`; its
authority is the plan contract itself (plan bundle, route evidence, D17
grant), so it has no plan-independent form (audit 2026-10-08, P-12).
