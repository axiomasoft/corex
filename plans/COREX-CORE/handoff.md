# HANDOFF — 2026-07-14 — after P1.14

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Opus/high — разбор эскалации требует архитектурного решения, не механики |
| Thinking | high |
| Context | NEW SESSION |
| Суть | Волна ремедиации P1 СТОИТ на P1.14. Item сам эскалировал (plan-protocol §10) дважды внутри собственного ТЗ — раньше, чем переходить к P1.15, владелец должен разрешить оба конфликта через ре-дизайн ТЗ item'а (путь по умолчанию ниже) либо, если решение реализуемо без изменения Scope/Deliverables, точечным фиксом + `resumeFromRunId`. |

````
/task:plan-design COREX-CORE P1.14
````

Читать сначала: `phases/P1.md` P1.14 (оба Escalation Needed целиком) → `findings/P1-audit-2026-07-13.md`
(A36, A92) → `findings/P1R-premortem.md`.

Альтернатива к ре-дизайну — точечный фикс без изменения Scope/Deliverables (если решение владельца
реализуемо в текущих рамках, напр. «второй phpstan-прогон без larastan под `tests/`» или «phpunit/phpcov
слияние clover-отчётов для coverage»): зафиксировать решение в plan.md (новый D#) и `open-questions.md`,
снять `git stash pop` (`stash@{0}` на `p1r-remediation` — см. Pending Work P1.14 в `phases/P1.md`, там же
полный список файлов в стеше), реализовать точечно, затем перезапустить волну через
`Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "<run-id волны>"})`.

Решение владельца нужно по обеим эскалациям P1.14 (детали — `phases/P1.md` P1.14 Escalation Needed,
две записи):
1. `phpstan.neon` `tests`-paths: 60 FP из-за Pest `$this`-биндинга статически невидимого PHPStan (8 файлов
   волны 1) — не относятся к `flex/modules-lint` (ради которых A36 просил `tests`). Варианты: (а) второй
   phpstan-прогон без larastan под 9 custom-правил на `tests/`+migrations — новый item; (б) официально
   сузить Deliverables P1.14 (снять `tests`); (в) ретрофит тестов волны 1 — отдельный item.
2. Coverage `--min=80` структурно недостижим без слияния PG/sqlite-лейнов (PG-only классы покрыты только
   `->group('pg')`-тестами, а `test:coverage` обязан их исключать — A92). Слияние лейнов в один процесс
   ломает `AuditLogTest.php` изоляцией по порядку (пре-существующий изъян P1.8, не P1.14). Варианты: (а)
   `phpunit/phpcov` — новая dev-зависимость, раздельные прогоны + merge отчётов; (б) добить недостающие
   ~24пп тестами в чужих доменах (Tasks/Models, SettingDefault) — отдельный item; (в) официально исключить
   PG-only namespaces из coverage `<source>` (ослабляет смысл item'а).

**После разрешения P1.14** волна продолжается штатно: P1.15 → P1.16 → P1.18 — цепочка по данным
(OQ-11 закрыт, см. Open risks), остальные items волны — по порядку плана.

**🔴 Пред-условие волны (закрыто):** OQ-11 (`open-questions.md`) — golden-шаблон P2 vs DDL-при-enable
решён Вариантом (A) 2026-07-14, владелец Dmitry Vostrikov. P1.18 переосмыслен под это решение
(purge = очистка данных, не DROP таблиц) — деталь в `open-questions.md` OQ-11.

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

**2026-07-14 — P1.14 попытан, но НЕ закрыт — статус 🔴 Blocked, волна ОСТАНОВЛЕНА**, ветка
`p1r-remediation` создана и запушена (коммиты `f4f8bd6` impl, `dab0337` docs):
- Реализовано и зелено в CI (run 29331681218, `gh run list --branch p1r-remediation`): job'ы `tests-pg`
  (postgres:16 сервис) — success; `anti-weakening-gate` — success; `quality` (PHPStan+Pint) — success;
  `tests` (все 4 php×laravel) — success. `on.push.branches` += `p1r-remediation`; `composer test:coverage` —
  без PG-зависимости; `phpunit.xml` `<source>` += `packages/modules/src`; `phpstan.neon` paths += миграции
  обоих пакетов (мимоходом починена FP `NoDirectSystemTableWriteRule` на собственных `mod_*`-миграциях);
  `NoForeignPrefixSqlRule` переписан на позиционную детекцию (A35) + негативная фикстура;
  `scripts/mutation-check.sh` (harness для всей волны) + 2 патча-саботажа в `plans/COREX-CORE/mutations/`;
  `scripts/anti-weakening-gate.sh` (CI-job, банит `->skip(`/`markTestSkipped`/`continue-on-error`/
  baseline/понижение `--min=`/уменьшение числа тестов).
- **Job `coverage` красный в том же прогоне** (56.3% < 80%) — структурная причина, не регрессия диффа
  item'а (PG-only классы покрыты только `->group('pg')`-тестами, а `test:coverage` обязан их исключать).
- **Item САМ эскалировал ДВАЖДЫ (plan-protocol §10) — обе записи целиком в `phases/P1.md` P1.14
  Escalation Needed:**
  1. `phpstan.neon` `tests`-paths: конфликт внутри собственного ТЗ item'а — Implementation Rules требует
     `tests` в paths буквально, Code Guidance того же item'а требует эскалации при находках вне Files.
     Факт: `tests` при `level: 6` поднимает 60 FP в 8 файлах волны 1 (Pest `$this`-биндинг статически
     невидим PHPStan) — ни одна не относится к `flex/modules-lint` (ради которых A36 просил `tests`).
  2. Coverage `--min=80` структурно недостижим без слияния PG/sqlite-лейнов — проверенная альтернатива
     (слить лейны в один процесс) ломает `AuditLogTest.php` изоляцией по порядку исполнения (изъян P1.8).
- **Решение: докс-курация 2026-07-14 понизила Status P1.14 с 🟠 Done with deviations до 🔴 Blocked** —
  два открытых Escalation Needed=yes не совместимы со статусом «сдан» по строгому критерию
  «Deliverables полностью выполнены» (plan-protocol §6), даже при зелёном первичном safety-критичном
  deliverable. Волна НЕ продолжается на P1.15+ до владельческого решения — см. Launch-block выше.
  Рабочее дерево на момент остановки сохранено: `git stash push -u -m "P1R-P1.14-partial"` → `stash@{0}`
  на `p1r-remediation` (детали, полный список файлов — Pending Work P1.14 в `phases/P1.md`).

**Remaining:**
0. **🔴 P1.14 разблокировать первым** — см. Launch-block выше. Ничего из п.1–3 не стартует раньше.
1. **Волна 2 (P1.15–P1.25)** — 11 items, СТОЯТ до разблокировки P1.14. Порядок после разблокировки не
   произвольный: P1.15 → P1.16 → P1.18 — цепочка по данным.
2. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
3. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D45) · phases/P1.md (26 items) · phases/P2.md ·
open-questions.md (OQ-1..15) · findings/{P1-audit-2026-07-13, P1R-premortem, P1R-coverage-matrix,
P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem, P1-premortem}.md ·
~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- **🔴 P1.14 Blocked — волна встала.** Две открытые эскалации (см. выше) требуют владельческого решения
  прежде чем волна двинется дальше P1.14. ~~OQ-11 (блокер DoR P1.18)~~ — закрыто Вариантом (A), 2026-07-14
  (владелец Dmitry Vostrikov, см. `open-questions.md` OQ-11); P1.18 переосмыслен под это решение.
- ~~**Код никогда не пушился**~~ — закрыто P1.14: ветка `p1r-remediation` запушена, run 29331681218
  (https://github.com/axioma-studio/corex/actions/runs/29331681218) — `tests-pg`/`quality`/`tests`/
  `anti-weakening-gate` зелёные; job `coverage` красный (56.3%<80%, структурная причина — см. P1.14
  Escalation Needed #2, не регрессия). Дальше волна пушится по каждому item'у на ту же ветку.
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
