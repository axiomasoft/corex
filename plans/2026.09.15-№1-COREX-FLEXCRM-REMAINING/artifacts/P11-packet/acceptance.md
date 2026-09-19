# P6.5 — CoreX remaining dependency acceptance

**Schema:** p11-consumer-reconciliation-acceptance/1
**Immutable:** create-only foreign receipt prepared by CoreX PLAN1 P11.5

Dependency-only acceptance for frozen PLAN1 requirements-lock against the released selected vector.
This receipt binds producer qualification and independently executed consumer pins/tests; it is not milestone closure.

## Bindings

| Field | Value |
|:--|:--|
| Requirements lock SHA256 | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| Acceptance map SHA256 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| Original parents | 365 |
| Requirements rows | 52R |
| Selected dependency atoms | 146 across 14 R |
| Released cohort SHA256 | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` |
| Released vector | `0.4.0-alpha.20260918` |
| Released commit | `0ca85d24edd631314c2ca410ea8b9fad4002419c` |
| Consumer pins report | `2f79ac7c868e692a0f0280f868d5461c63c807f73621c6e2154d148bb349a5ef` |
| Consumer tests report | `be2c2811ee2a2f748eae4cb3a810e98701f3b7abf66bd29e557c04c8b5f9347c` |
| Dependency ledger report | `d15dbfadf10fa5415872da43a48709b23ac04c6e94908479d7b906127e1b7a75` |
| Accepting actor | FlexCRM consumer owner / crm-consumer-tests-20260919 |

## Dependency scope

R47 final action is excluded from prerequisite closure; R52 invariant verified in ledger.
R41/R42 dependency slices close here; full app DB/composition/product criteria remain FlexCRM-owned.

## Explicit non-grants

- Does not close FLEX-2 P6.5/P6.17/P6.18 or issue milestone-close commands.
- Does not grant G-CONSUMER-RECONCILE; foreign write remains separately authorized.
- P10 effective seal remains readiness-only (`required_gaps=169`).

**Payload SHA256:** `1da78cc844b53a2d9f1d268c886d651495b708fcaf212c9a8ebdb50413c7eb07`
