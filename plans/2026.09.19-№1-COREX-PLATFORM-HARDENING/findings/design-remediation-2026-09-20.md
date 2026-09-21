# Design remediation evidence — DA01–DA14

Design-level evidence only. The original RED audit is immutable; repeat audit was not run.

| DA | Contradiction removed | Owning evidence | Required witness |
|:--|:--|:--|:--|
| DA01 | Dynamic default connection retained. | D100; P7.1/P7.12; P9.2 | same singleton central→tenant→central |
| DA02 | Effect and success outcome share one tenant transaction. | D104; P13.5/P13.6 | crash rolls back; retry effects once |
| DA03 | Replay rechecks current authority without handler. | D105; P13.6/P13.8/P13.12 | HTTP→revoke→tool denial/prune; count=1 |
| DA04 | Published means durable acceptance. | D99; P5.7/P5.12; P14.12 | offline consumer→restart→one inbox effect |
| DA05 | History is separate from current key reservations. | D102; P10.3/P10.4 | repeated revisions; expand→switch→contract |
| DA06 | Registry fingerprint is provenance only. | D103; P11.4/P11.5/P11.8 | registry drift replays; identity change conflicts |
| DA07 | Roave uses package cwd, candidate commit, strict exits. | D98; P3.4 | dirty removal=3; infra errors RED |
| DA08 | PackageRegistrar is a declared public seam. | D101; P6.2/P8.1/P8.4/P8.5 | real registrars pass R4 |
| DA09 | Closed zone map names introduced Support types. | D101; P6.2/P7.1/P8.1/P8.4/P8.5 | unknown Support import fails |
| DA10 | ResourceId covers UUIDv7/UUID/ULID/int. | D106; P13.8/P13.12/P17.3 | four canonical round-trips |
| DA11 | Expired/corrupt publications terminate. | D99; P5.6/P5.12 | exhaustion/malformed reach dead |
| DA12 | Attributes is producer-adjusted Profile B. | D107; P10.8/P16.11/P16.13/P17.10 | fresh census and B no-move verdict |
| DA13 | Missing/null/zero/positive TTL differ. | D100; P7.1 | four-case matrix |
| DA14 | P1.2 has an explicit bounded manifest. | D108; P1.2 | exact 23-path expanded set; subtree-only diff |

Roadmap now makes P10 depend on P4/P5/P7 and P16 consume P14. Handoff remains
`DESIGN-ROOT-TERMINAL`; no execution or automatic audit follows this remediation.

Corrective pass reconciled the report with the actual consumer carriers, removed the stale
P16.3 attributes owner, made all six P5.12 witnesses explicit, included receipts in Files, and
split D100 proof into P7.12 so P7.1 retains the ordinary eight-file limit. No repeat audit ran.
