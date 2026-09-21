---
id: D45
date: 2026-09-19
status: accepted
item: P8
items: [P8, P8.6]
supersedes: []
superseded_by: null
---
# D45 — гейт размера считает только ServiceProvider, порог 20 плюс ratchet

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:✅ P8-Q5 ThoughtWorks fitness functions, Pest Arch.
RAG:— baseline `00-baseline-metrics.md` §3: tenancy 23/5/2, modules 12/0/0;
`CoreServiceProvider` — 18 `singleton`/`scoped`. A74: симптом «>15–20
bindings». D17: Deptrac/PHPat не вводятся; сканер — `token_get_all`.

## Решение

1. Гейт считает **call site** `$this->app->singleton|scoped|bind|bindIf`
   только в классах, расширяющих `Illuminate\Support\ServiceProvider`,
   под `packages/*/src`. Registrar'ы, трейты и замыкания вне провайдера
   не считаются.
2. Жёсткий порог — **20** (верхняя граница A74). Цикл
   `foreach (... as $bootstrapper) { $app->singleton($bootstrapper); }`
   — один call site.
3. Ratchet: JSON-снимок числа call site на каждый `*ServiceProvider.php`.
   Рост относительно снимка — нарушение, даже если число ≤ 20.
4. `CoreServiceProvider` в P8 **не** дробится. Его число замораживается
   ratchet'ом (E-CORE-PROVIDER). Декомпозиция ядра — не эта фаза.
5. Посадка обязана назвать файл и выйти 1. Observational режим не
   объявляется: после декомпозиции tenancy/modules правило сразу
   блокирующее.

## Почему

Считать registrar'ы дало бы ложные срабатывания на связный модуль и
научило бы прятать биндинги в хелперы, не уменьшая composition root
(`RAG:✅` P8-Q5). Порог 15 заставил бы дробить ядро или intl в этой
фазе — вне скоупа. Порог без ratchet оставил бы Core 18 с запасом
роста. Альтернатива «перевести singleton в scoped в P8.6» отклонена:
доказательство неизменности фазы сравнивает lifetime; смена lifetime —
работа allowlist P4.12, не декомпозиции.

## Consequences

P8.6 пишет `scripts/provider-size-gate.php` и снимок после
декомпозиции. P4.12 по-прежнему владеет гейтом конструкторов
singleton'ов. Смена `singleton` → `scoped` в P8 не делается.
