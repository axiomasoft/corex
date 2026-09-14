---
type: workflow
tags: [corex, plan-v2, successor]
created: 2026-09-12
---

# 2026.09.12-№1-COREX-SUCCESSOR — План №1 — CoreX: v2-преемник архивного COREX-CORE

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | 2026.09.12-№1-COREX-SUCCESSOR |
| Title | План №1 — CoreX: v2-преемник архивного COREX-CORE |
| Layout | v2 |
| Document Type | Executable Master Plan |
| Authoring Model | frontier/high · gpt-5.6-sol |
| Repository | corex |
| Related Packages | corex/core, corex/modules, corex/auth, corex/tenancy |
| Execution Mode | phase-first |
| Target Operator Classes | implementation/high (code) · frontier/high (decision-ready design) |
| Approval Owner | Dmitry Vostrikov |
| Home | vault:CoreX/plans |
| visibility | private |
| Historical Source | COREX-CORE, archived 2026-07-28, read-only |
| Paused By | — |

## 1. Context

COREX-CORE P1–P3 завершены; этот план НЕ supersedes их результаты, НЕ мигрирует архив и НЕ повторяет терминальные items.
На дереве `82abc18` остались три узких исполнимых разрыва регрессионной защиты и несколько неразрешённых границ ответственности.
Исторический Pending Work проверен против кода, актуальных Brain-источников и GitHub API 2026-09-12 (D1/D2).
P1 расширяет защиту пакетных тестов и проверяет обещанную L12-совместимость tenancy; P2 доказывает положительную record-ветку department scopes.
P3 производит decision-ready предложения и owner requests по несовпадающим нормам, tenancy/auth seams и публикации.
Завершение P3 design item НЕ означает, что соответствующая runtime-фича реализована или что владелец выдал approval.
Scope НЕ включает новые продуктовые модули, marketplace, webhook engine, AI, UI, release/tag/push или публичные Contracts.
Канонический дом — Brain/CoreX/plans; `brain sync plans CoreX` поддерживает исполнимый repository mirror.
Операционные bundles/гейты собираются из repository mirror в cwd CoreX: кодовые пути относительны корню репозитория, не vault.
Нормативные Brain-входы минимизированы и закреплены в D2; архив не является обязательным execution input.

## 2. Execution Rules

- Выполнять только после отдельного запуска владельцем; design invocation не выполняет successor.
- Читать `docs/dev-doctrine.md`; CoreX = L0 для любого Laravel-проекта. Никаких `flex/*` зависимостей или продуктовых идентификаторов в `corex/*`.
- Публичные `src/Contracts/**` не менять: отдельный major/owner gate обязателен, включая аддитивный новый public interface.
- P1/P2 не меняют реализации правил, runtime-поведение, dependency constraints или coverage floors.
- Новые тесты — в `packages/<pkg>/tests/**`; корневой `tests/` остаётся core/modules harness.
- Existing D17 pre-1.0 minor allowance НЕ даёт этому плану право менять Contracts.
- Все статусы и state/views — только journal + владельцы генераторов, не ручные правки.
- Обычные batches recommended: исполнима dependency-safe ready subset, закрытие каждого item сохраняется.
- Для code item: D34 оба root lanes, affected package lanes; source changes требуют D98 mutation-check.
- PG-тесты допускаются только после preflight и проверки изолированной test DB; никаких dev/prod `migrate:fresh`.
- `Files ∩ digest_inputs` reconciles через evidence-graph: spec → product → closure только при реальной self-invalidating collision.
- P3 меняет только указанные новые decisions/queue; существующие Brain docs и frozen DB_SCHEMA без owner approval не править.
- Не дописывать ретро-коды границ, не чистить исторические CHANGELOG, не линтить/реопенить COREX-CORE.
- External CI, secrets, forks, orgs, split mirrors, tags, Packagist и DNS: только отдельная явная owner authority.
- GREEN acceptance direct-closes фазу; Review не является audit consent; audit-admission/v2 не выдан.
- Живой CI результат именовать с run/job/SHA; локальный прогон не выдавать за runner proof.
- До handoff: owned views/bundles, focused lint в repository mirror и синхронизация только plans; чужую docs divergence сохранять.

## 3. Routing

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| B1 | P1.1 | implementation/high | plan-exec | light | recommended: общий quality-config, package inventory и D34 setup с P1.2; проверить границу намеренных негативных fixtures |
| B1 | P1.2 | implementation/high | plan-exec | light | recommended: тот же repository/CI контекст; L12/L13 resolver и PG16 lanes без изменения runtime promises |
| solo | P2.1 | implementation/high | plan-exec | full | Отдельная phase-local auth security seam и independent fixture review: reuse B1/B3 unsafe из-за phase closure; bounded review положительных/отрицательных department fixtures |
| B3 | P3.1 | frontier/high | manual | light | recommended: общий D1/D2 и owner-debt контекст с P3.2/P3.3; только decision-ready нормы без изменения источников |
| B3 | P3.2 | frontier/high | manual | full | recommended: общий debt context; authority/security/schema вопросы требуют проверки именно proposal, не исполнения |
| B3 | P3.3 | frontier/high | manual | light | recommended: общий owner/release context; proposal сохраняет private dependency и external authority boundaries |

## 4. Update Log

Owned projection: `status.md`; журнал `journal.jsonl` владеет update history, статусами и authoring actor/model provenance.

## 5. Decision Log

Каталог решений: `decisions/`; собрать: `python3 plan-views.py decisions --plan-dir <plan>`.

## 7. Authority

User brief и текущий design-finish запрос разрешают авторинг/qualification нового плана; ограниченное добавление обязательных carriers закреплено D10.
P1/P2 frozen test/CI scope рекомендован к отдельному исполнению, не запущен.
P3 acceptance = проверяемый decision-ready пакет; нет tacit approval на schema/public-contract/release изменения.
