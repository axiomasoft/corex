---
id: D111
date: 2026-09-22
status: accepted
item: P5
items: [P5, P5.6, P5.13, P5.14, P5.15, P5.16, P5.17, P5.18, P6, P7, P8, P9, P10, P11, P12, P13, P14, P15, P16, P17]
supersedes: [D37]
superseded_by: null
---
# D111 — Risk-routed execution after the P1–P5 audit

**Actor:** owner (Dmitry Vostrikov), 2026-09-22; recorded by plan-design.

**Evidence:** `findings/execution-audit-P1-P5-2026-09-22.md`; owner request to prepare
the plan for Sol, Grok or Composer; D37; official model capability references recorded in the
audit session.

## Решение

D37 remains historical evidence for phase-detailing choices, but its rule “Items — Composer 2.5”
is superseded. Remaining execution is routed by risk:

| Work | Primary route | Independent review |
|:--|:--|:--|
| bounded mechanical, one package, no public/DDL/provider boundary | Composer 2.5, medium/high | light or declared full |
| cross-package provider or public-surface integration | Grok 4.7, high/xHigh | Sol high when phase-closing |
| DDL, durable/public/security boundary, joined qualification | GPT-5.6 Sol, xHigh/max | full; author session cannot self-close material findings |
| P17 final release qualification | GPT-5.6 Sol max; Astra remains optional owner escalation | full independent review |

For Cursor-native execution, the intended slugs are `composer-2.5`, `cursor-grok-4.7-high`
(or the account's current Grok 4.7 xHigh slug), and `gpt-5.6-sol`. A launcher must verify the
actual available slug; an unmapped identity fails before product mutation.

P5 remediation routes are fixed in `plan.md` §3.1. P6–P8 default to Grok 4.7 for
cross-package integration, with Composer retained only for leaf items that meet the bounded rule.
P9–P14 default to Sol for tenancy, durable, public API and security work. P15–P16 may use
Composer for mechanical leaves, but joined phase qualification is Grok 4.7 or Sol. P17 uses Sol.

## Почему

Composer 2.5 produced locally passing code but closed P5.11 with known red root gates, violated
P5.12 scope/dependency ordering and left P5.6 without terminal evidence. The failure mode is not
raw coding inability; it is cross-package attribution and closure discipline. Routing therefore
changes at the integration boundary rather than replacing Composer everywhere.

## Consequences

- P6.1 is blocked by P5.18.
- A red root gate is owning repair unless reproduced on the pre-item baseline.
- Provider/public-contract items list reverse consumers in Validation.
- Phase-closing qualification is performed by a different model/session from material product
  implementation when possible.
- Existing D37 descriptions of completed design sessions remain historical facts; only its live
  execution/model recommendation is replaced.
