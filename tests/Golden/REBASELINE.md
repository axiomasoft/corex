# Golden rebaseline log

Baseline changes require an intentional surface change under PLAN3.D40.

| Дата | Решение | Слои | Причина |
|:--|:--|:--|:--|
| 2026-09-28 | PLAN3.D40 | all | initial baseline P01.2 |
| 2026-09-28 | PLAN3.D44 | php-surface | P02.1: package-owned compiler/config sections and public Testing factories; routes/events/wire preserved |
| 2026-09-29 | PLAN3.D44 | php-surface | P02.2: +6 types, ~2 changed (golden-delta OK); api/v1, routes/events/wire preserved |
| 2026-09-29 | PLAN3.D64 | php-surface | P04.1: seven new Attribute/scanner types, no existing effective record changes; routes/events/wire preserved; candidate and independent review in plans/2026.09.27-№1-COREX-KERNEL/artifacts/P04.1-execution |
| 2026-09-30 | PLAN3.D64 | php-surface | P04.2: scoped native contract closure and owner-module moves; semantic delta in artifacts/P04.2-execution/golden-semantic-delta.json; routes/events/wire preserved; independent review deferred by owner to a new session |
| 2026-10-01 | PLAN3.D64 | php-surface, wire | P04.3: typed package error identities, compiled ProblemRegistry and coded exceptions; named delta in artifacts/P04.3-execution/golden-semantic-delta.json; routes/events/promoted fixtures preserved |
| 2026-10-01 | PLAN3.D64 / PLAN3.D74 | php-surface | P10.2: six dev tooling moves and shared Internal registry; semantic delta in artifacts/P10.2-execution/golden-semantic-delta.json; existing promoted fixtures and routes/events/wire preserved |
