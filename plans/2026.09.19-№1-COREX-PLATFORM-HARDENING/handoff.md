# HANDOFF — 2026-09-21 — after P2.3 + P2.4

## ⚠ LAUNCH — обязательный вывод в чат (plan.md Execution Rules)

Исполнитель **в конце каждой** `task:plan-exec` / handoff **копирует этот блок в ответ владельцу**
без сокращений. Одна строка `task:plan-exec …` без `--model` — дефект.

| Model class | Effort | Native `--model` |
|:--|:--|:--|
| implementation | medium | `composer-2.5` |

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P2.5'
```

**Next:** `task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P2.5`

| Параметр | Значение |
|:--|:--|
| Batch | solo |
| Model class | implementation |
| Effort | medium |
| Native model | `composer-2.5` (pin §3.1 P2.5 · plan.md Execution Rules) |
| Capabilities | repository read/write; PHP scanner; plan lifecycle |
| Context | continue-root |
| Суть | Пер-классовые вердикты + Core Litmus Test → `verdicts.csv` (§7). |

**Done:** P2.3: `TableExtractor`, CLI `tables`; `artifacts/architecture/tables.csv` (99 таблиц);
`docs/architecture/table-ownership.md` (шапка §5.4 + sys_ §5.3 + таблица); 4 пакета-владельца sys_*;
`aut_role_assignments` exception D102; receipt §P2.3; journal closed-green.
P2.4: `ConfigExtractor`, CLI `config`; `artifacts/architecture/config-keys.csv` (161 ключ);
`docs/architecture/configuration.md` (шапка §6.3 + таблица); CFG-006 corex-intl-search;
17 undeclared-read; receipt §P2.4; journal closed-green.
composer lint:check зелёный.

**Remaining:** P2.5 (вердикты + Core Litmus Test), P2.6 (scorecard).

**Open risks:** `git diff packages/*/composer.json` от P1.2 — не от P2; гейт graph не трогали.

**Sources of truth:** `journal.jsonl`; `phases/P2/P2.5.md`; `research/P2-design.md` §7;
`artifacts/architecture/files.csv`; `findings/P2-inventory-receipts.md`.

**Workarounds / Deferred / Open questions:** Q1/Q2 без изменений.
