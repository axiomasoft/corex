# HANDOFF — 2026-09-28 — after P00

**Next:** design-finish: task:plan-design 2026.09.27-№1-COREX-KERNEL finish

| Параметр | Значение |
|:--|:--|
| Batch | n/a |
| Model class | frontier |
| Effort | high |
| Capabilities | — |
| Context | continue-root |
| Суть | Whole-plan finish по детализированным фазам P00–P01: execution-sheet строки P01, fingerprint; затем design audit. |

```text
$ task:plan-design 2026.09.27-№1-COREX-KERNEL finish
```

**Done:** P01 детализирована: `phases/P01/P01.md` + P01.1 (окружение PHP 8.4, манифесты D36/D37), P01.2 (golden D38),
P01.3 (ownership v2, PHPStan max, dependency-analyser — D37/D39), P01.4 (CI supply chain D18/D39). Решения D36–D39.
Досье `research/P01-design.md` (§1 карта кода, §4 runbooks, §5 playbook, §6 self-check, §7 owner gates),
`research/P01-design-rag.md` (E1–E7), captures `artifacts/P01-design-rag/` (2 http + 2 perplexity), факты и примеры
`artifacts/P01-design/`, проверенные скрипты `manifest-check.py` (сейчас 154 нарушения = рабочий список P01.1),
`junit-summary.py`, `golden-rename-map.py` (666 class / 16 package / 3 ambiguous). Routing: solo P01.1; B01-B P01.2+P01.3;
solo P01.4 — все implementation/high, full.

**Remaining:** finish (execution-sheet), `task:plan-audit 2026.09.27-№1-COREX-KERNEL design`, затем исполнение P01.1 (solo).

**Sources of truth:** `plan.md` §3, `phases/P01/`, `research/P01-design.md`, `decisions/D36–D39`, `roadmap.md`.

**Open risks:** Owner gates до исполнения P01.1: O-1 `sudo apt install php8.4-xml php8.4-intl php8.4-sqlite3 php8.4-bcmath php8.4-zip php8.4-gmp php8.4-gd php8.4-pcov`;
O-2 роль/БД `corex` в системном PostgreSQL 16 (команды — `research/P01-design.md` §7). O-3 push + live CI — граница фазы.

**Workarounds/Deferred/Open questions:** Stancl alpha-пины и AzGuard `@dev` — временные исключения D36 п.3 (снятие P07).
Roave report-only и SBOM — P09. Входы `zizmor-action` v0.6.4 — VERIFY-AT-EXEC при pin.
