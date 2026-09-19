# HANDOFF — 2026-07-28 — after P1

**Next:** план закрыт

**Done:**

- **Фаза P1 повторно закрыта 🟠 Done with deviations (plan-close/sonnet-low, 2026-07-28).** Все 31
  items терминальны (5🟢/26🟠) — `⬜` не осталось. Status Board строка P1 в `plan.md` исправлена
  `🟢 Done` → `🟠 Done with deviations` (мисматч: борд утверждал зелёный агрегат при 26 items в
  🟠). `## Phase Handoff` в `phases/P1.md` перезаписан целиком: включены P1.27 (coverage-метрика),
  P1.28 (второй phpstan-прогон `flex/modules-lint` на `tests/`), P1.29 (rethrow `upgrade()` +
  parent-workspace guard `move()`), P1.30 (PHP 8.5 предикат), P1.31 (boxed-лейн 8.3, дубль-require
  `stancl/tenancy` снят) — все добавлены к фазе ПОСЛЕ первого закрытия (2026-07-15, P1.26) как
  отдельные items, реопена фазы не потребовалось (борд не переходил в 🟡). Docs-sync: не требуется
  — все пять item'ов чинят CI-гейты/корректность данных, новых процессов/фич для `docs/` нет.
  Незапланированные untracked пути в дереве (`.codex/`, `.mcp.json`, `.serena/`, `docs-public/`,
  `scratch_gate.log`) — вне `Files` фазы P1, не коммитятся этим закрытием.
- **План COREX-CORE закрыт ЦЕЛИКОМ.** Все три фазы терминальны: P1 🟠 Done with deviations
  (5/31 🟢), P2 🟠 Done with deviations (10/25 🟢), P3 🟠 Done with deviations (1/8 🟢). `plan.md`
  Meta Status `🟡 In progress` → `🟠 Done with deviations`. `plans/ACTIVE.md` сброшен на `—`.

**Remaining:** ничего в рамках плана — все items трёх фаз терминальны. План архивирован
(`/task:plan-close archive COREX-CORE`, 2026-07-28) — следующий шаг вне протокола.
**Sources of truth:** plans/archive/COREX-CORE/plan.md · plans/archive/COREX-CORE/phases/P1.md ·
plans/archive/COREX-CORE/phases/P2.md · plans/archive/COREX-CORE/phases/P3.md

## Archive migration checklist (2026-07-28, plan-close/sonnet-low)

- **Перенос:** vault `05-Projects/03-Packages/CoreX/plans/COREX-CORE/` →
  `~/Vaults/Brain/plans/archive/COREX-CORE/` (git mv, vault-коммит `21b55e9`); репо-симлинк
  `corex/plans/COREX-CORE` перевешан на `plans/archive/COREX-CORE` → новый vault-путь.
- **root/:** каталога `root/` в плане не было (структура — `brief/`, `findings/`, `research/`,
  `phases/`, `workflows/`, `plan.md`, `handoff.md`, `journal.jsonl`, `open-questions.md`,
  `roadmap.md`) — миграция в `docs/` ПРОПУЩЕНА (нечего мигрировать).
- **Осталось в архиве целиком:** `brief/`, `findings/`, `research/`, `phases/`, `workflows/`,
  `plan.md`, `handoff.md` (этот файл), `journal.jsonl`, `open-questions.md`, `roadmap.md`.
- **Реестры/ссылки обновлены (corex-репо):** `CLAUDE.md` (`plans/COREX-CORE/` →
  `plans/archive/COREX-CORE/`), `docs/dev-doctrine.md` (13 вхождений `plans/COREX-CORE` →
  `plans/archive/COREX-CORE`); `plans/ACTIVE.md` уже нёс `—` (сброшен закрытием фазы P1).
**Open risks:** остаточные WARN линтера (пре-существующие, не разбирались этим закрытием) и
Pending Work P1.28 (пакетные сьюты `packages/*/tests/**` вне периметра `phpstan-tests-lint.neon`)
— кандидаты для будущей волны, не блокируют закрытие плана.
**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: пакетные сьюты `packages/*/tests/**` под `phpstan-tests-lint.neon` (P1.28 Pending Work)
- open_questions: —
