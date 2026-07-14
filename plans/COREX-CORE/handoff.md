# HANDOFF — 2026-07-14 — after P1.13

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Opus (Blocker-ярус) / Sonnet (механика) — per-item, пинит сам workflow |
| Thinking | high на P1.15/P1.16/P1.18/P1.19/P1.21/P1.23, medium на остальных |
| Context | NEW SESSION — Workflow-orchestrated (не продолжать эту сессию) |
| Суть | Волна 2 фазы P1 — ремедиация по 🔴 RED-аудиту: 12 items на ветке `p1r-remediation`, каждый: impl → верификатор → adversarial review → close. **Предусловие: владелец закрыл OQ-11.** |

````
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js"})
````

**🔴 ПЕРЕД ЗАПУСКОМ — решение владельца по OQ-11** (`open-questions.md`): golden-шаблон P2 vs
DDL-при-enable. Вариант (A) «шаблон содержит таблицы всех модулей» означает, что **P1.18 — самый
дорогой item волны — строит не то** (purge должен чистить ДАННЫЕ, а не дропать таблицы). Запуск волны
без этого решения рискует выбросить работу P1.18 целиком. Остальные items от OQ-11 не зависят —
при желании можно стартовать волну, а P1.18 придержать.

Волна НЕ исполняется через `/task:plan-exec`: несущие items предписаны `opus/high`, команда пинит
sonnet/medium (routing-undershoot запрещён — plan-protocol §9, D35). Именно этот undershoot дал
2 из 3 Blocker'ов волны 1.

После зелёной волны (порядок обязателен): аудит фазы P1 (команда plan-audit — фаза станет терминальной
только когда все 12 items волны сданы; до тех пор аудит не запускать).

Аудит проводится **ВСЛЕПУЮ** (без A-списка находок — иначе он превращается в сверку по чек-листу,
D45/P1.26 Code Guidance). GREEN → item **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` +
semver-теги + PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D45) → этот файл →
`phases/P1.md` (ТЗ item'а) → `findings/P1-audit-2026-07-13.md` (что чиним, A1..A93) →
`findings/P1R-premortem.md` (что волна НЕ закрывает) → `findings/P1R-coverage-matrix.md` (93 → items).

**Done:** Волна 1 (P1.1–P1.13) исполнена и закоммичена. **Аудит 2026-07-13 — 🔴 RED: 93 находки**
(3 Blocker · 17 Major · 53 Minor · 20 Nit). Гейты были зелёные — они не покрывали заявленное.

**2026-07-14 — фаза переоткрыта и ре-спроектирована** (`/task:plan-design COREX-CORE P1`):
- статусы 12 items понижены 🟢→🟠 (материальные отклонения §6), Completion Notes сохранены как история;
- **13 новых items P1.14–P1.26**; решения **D28–D45**; **OQ-10..OQ-15**;
- полный список находок восстановлен из журнала прогона в `findings/P1-audit-2026-07-13.md`
  (в фазе жили только Blocker/Major) + матрица покрытия 93/93;
- **pre-mortem волны (3 линзы)** нашёл 6 дыр в самой ремедиации — все внесены (D39–D45).

**2026-07-14 — P1.14 закрыт (🟠 Done with deviations), ветка `p1r-remediation` создана:**
- CI: job'ы `tests-pg` (postgres:16 сервис), `coverage`, `anti-weakening-gate`; `on.push.branches` +=
  `p1r-remediation`; `composer test:coverage` — без PG-зависимости; `phpunit.xml` `<source>` +=
  `packages/modules/src`; `phpstan.neon` paths += миграции обоих пакетов (мимоходом починена FP
  `NoDirectSystemTableWriteRule` на собственных `mod_*`-миграциях); `NoForeignPrefixSqlRule` переписан на
  позиционную детекцию (A35) + негативная фикстура; `scripts/mutation-check.sh` (harness для всей волны) +
  2 патча-саботажа в `plans/COREX-CORE/mutations/`; `scripts/anti-weakening-gate.sh` (CI-job, банит
  `->skip(`/`markTestSkipped`/`continue-on-error`/baseline/понижение `--min=`/уменьшение числа тестов).
- **Эскалация (не блокирует волну, см. phases/P1.md P1.14 Escalation Needed):** `phpstan.neon` НЕ получил
  `tests` в paths — конфликт внутри ТЗ item'а: буквальное требование против собственного же Code Guidance
  item'а («вскрыло ошибки вне Files — эскалация, не фикс»). Причина факта: `tests` при `level: 6` поднимает
  60 ошибок в 8 файлах волны 1 — Pest `it()`/`beforeEach()` рантайм-биндит `$this` к `TestCase`, статически
  PHPStan видит `Pest\PendingCalls\TestCall` (мимо `@mixin`); НИ ОДНА ошибка не относится к `flex/modules-lint`
  (ради которых A36 и просил `tests`). Owner-decision нужно: (а) отдельный phpstan-прогон только под
  9 custom-правил на `tests/`+migrations, без larastan; (б) официально сузить Deliverables P1.14; (в) отдельный
  item — ретрофит тестов волны 1 под статически-типизируемый паттерн.
- **CI-верификация (D45):** см. `gh run list --branch p1r-remediation` на момент закрытия — приложено в
  Completion Notes item'а или в отдельном апдейте этого файла, если пуш случился после него.

**Remaining:**
1. **Волна 2 (P1.15–P1.25)** — 11 items. P1.14 закрыт первым (см. выше). Порядок дальше не произвольный:
   P1.15 → P1.16 → P1.18 — цепочка по данным.
2. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
3. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D45) · phases/P1.md (26 items) · phases/P2.md ·
open-questions.md (OQ-1..15) · findings/{P1-audit-2026-07-13, P1R-premortem, P1R-coverage-matrix,
P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem, P1-premortem}.md ·
~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- **🔴 OQ-11 (блокер DoR P1.18):** golden-шаблон vs DDL-при-enable — при варианте (A) P1.18 строит не то.
- **Код никогда не пушился:** 28 коммитов волны 1 не в `origin`, `origin/main` от 2026-07-07 — CI не видел
  ни строчки кода P1. До закрытия P1.14 (ветка + push + зелёный `tests-pg`) любое «зелено» локально.
- **Модуль-система нерабочая в дефолтной конфигурации** (A1: `tags()` на не-taggable сторе) — до P1.16.
- **Компиляцию реестра не вызывает никто** (ни composer-хук, ни CI, ни деплой), а пустой реестр молчит —
  до P1.15 прод получил бы «систему без модулей» без единой ошибки.
- **`corex/core` не поставляет ни одной применимой миграции** (A19/A62) — до P1.19 `migrate` у потребителя
  создаёт ноль `sys_*`-таблиц.
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65): зелёная сьюта ≠ покрытие. Правило волны —
  mutation-check патчами в репо, прогоняет отдельный верификатор.
- **Ядро де-факто PG16-only, но нигде этого не объявляет** (OQ-12) — boxed-заказчик узнает на площадке.
- В рабочем дереве **чужой дифф** (`.gitignore`, `CLAUDE.md`, удалённый `AGENTS.md`, `.claude/**`) — не мой
  и не items волны: исполнителям запрещено его коммитить.
- OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза (D8/D25).
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).

**Workarounds/Deferred/Open questions:**
- workarounds: хвосты волны 1 адресованы поимённо — `findings/P1R-coverage-matrix.md` (93 кода → item).
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7) — без него ретеншн-шов
  P1.21 в проде не исполним; fan-out db-poll (1 поллер на аккаунт — стена облачного парка); ленивая
  гидрация реестра (бюджет бута B-10 §7.1); Infection/MSI как машинный оракул тестов; расширяемость
  `Capability`/`Extend\*` для спутников (U4); референс-модуль `flex/module-example` (U5); corex/components
  (D6); DX-генераторы (D26).
- open_questions: **OQ-1..15**. Блокеры: **OQ-11** (DoR P1.18), OQ-5 (P2.1), OQ-15 (P1.26), OQ-7 (P2.10/P2.11).
  Ждут владельца: OQ-12 (PG16 hard requirement), OQ-13 (root vs sys feature flags), OQ-14 (реестр префиксов),
  OQ-6 (DB_SCHEMA), OQ-10 (телеметрия devloop — вне плана).
