# P9.1 receipt

Post-P8 дерево `packages/tenancy/src`: 174 PHP-файла, coverage 100%,
duplicates 0, unique from/to. Карта: `findings/P9-tenancy-map.md`.
Fingerprint: `findings/P9-compatibility-baseline.json`
(`p9-compatibility-baseline/v1`).

Распределение по item: stationary 49, P9.2 25, P9.3 55, P9.4 20,
P9.5 10, P9.6 12, P9.7 3. Единственная разрешённая behavioral
binding-дельта — D49: scoped `CoreX\Tenancy\Contracts\TenantContextRestorer`
→ `CoreX\Tenancy\GuardedTenantContextRestorer`,
`only_permitted_behavior_delta=true`. Listeners 7, bootstrapper FQCN
разрешены через use-alias. `double_init_revert.measured=false`, owner P9.2.

D48: multi-account relay owner `Tenancy/Runtime`. Scheduler, lease,
DDL и concurrency policy не построены. Риск голодания D27 остаётся
открытым до измеренного триггера (starvation, SLO breach, manual fan-out).

Живое дерево ещё не 13 context-каталогов: `check` сверяет committed
baseline, а не требует текущий layout. Негативные fixtures красные
по своей причине.

## Validation

```
python3 scripts/tenancy-structure-gate.py check
exit 0
baseline accepted files=174 duplicates=0
```

```
python3 scripts/tenancy-structure-gate.py fixture fourteenth-root
exit 1
fixture fourteenth-root rejected: fourteenth-root
```

```
python3 scripts/tenancy-structure-gate.py fixture membership-internal
exit 1
fixture membership-internal rejected: membership-internal
```

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 174/174, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":495,"passed":488,"skipped":7,"assertions":1724}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

`composer test:pg` на грязном дереве сначала падал в
`MigrationPublishTest` (12 sys_* миграций и timestamptz без precision 6
у незакоммиченных outbox/inbox). Это чужой messaging slice, не P9.1.
Чтобы заявленный carrier стал зелёным, у этих миграций выставлен
`precision: 6`, а ожидание publish-теста стало 12. Эти правки не входят
в commit P9.1.
