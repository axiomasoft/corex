# HANDOFF — 2026-07-08 — after P7.3

**Next:** план закрыт (Status → 🟢 Done, все 13 items 🟢). Нет следующего item'а внутри плана.
Возможные следующие шаги — вне plan-протокола, отдельными решениями пользователя: (1)
vault-handoff волны (ADR-ревизии 011/014/015-supersession-note, Architecture-доки — Scope Excluded
P7.3); (2) реализация P0–P5 `50_execution/00_EXECUTION_ROADMAP.md` (`task:plan-design`/`task:plan-exec`
по фазам — corex ещё не начат для module/component/licensing-слоя); (3) продолжение параллельной
волны FLEXCRM-W2 (домен-спеки B-27+, свой план `plans/FLEXCRM-W2/`, независим от FLEX-CODE).
**Done:** Все 7 фаз/13 items плана. P1.1–P1.3 (код-аудит/конвенции/карта), P2.1 (анатомия модуля),
P3.1–P3.2 (компонентная модель + визуальный редактор), P4.1–P4.2 (лицензирование client-side), P5.1
(дистрибуция/установка стык RegHub), P6.1 (композиция продуктов), P7.1 (C-99 адверсариальная сверка,
вердикт был ATTENTION), P7.2 (D-007 арбитраж 5 находок C-99, C-99 → OK), **P7.3** (MASTER_PLAN «Этап
10» + roadmap §3.3 traceability + финальное закрытие плана).
**Remaining:** — (план терминален).
**Sources of truth:** `plans/FLEX-CODE/plan.md` · `plans/FLEX-CODE/phases/P1..P7.md` ·
`plans/flex/20_research/R-30_code-audit.md..R-33_licensing-practices.md` ·
`plans/flex/30_blueprints/C-01_code-conventions.md` · `C-02_module-anatomy.md` ·
`C-03_component-model.md` (правлена P7.2) · `C-04_licensing-client.md` ·
`C-05_distribution-install.md` (правлена P7.2, §9) · `C-06_product-composition.md` (правлена P7.2,
§9) · `C-99_consistency-code.md` (вердикт OK) · `plans/flex/10_decisions/
D-007_code-wave-arbitrage.md` · канон: B-03/B-10/B-17/B-22/B-23/B-25 ·
`plans/flex/10_decisions/D-003_naming.md` · `plans/flex/00_MASTER_PLAN.md` §5 «Этап 10» (новая
секция, P7.3) · `plans/flex/50_execution/00_EXECUTION_ROADMAP.md` §3.3 (traceability-таблица
C-01..C-06, новая, P7.3) · RegHub: `05-Projects/03-Packages/RegHub/docs/03_Dev/
{Installation_Distribution_Spec,Package_Manifest_Spec,Domain_Model_RegHub}.md` + ADR-006.
**Open risks:** Компонентный контракт C-03 закрыт и согласован с B-10/C-02/B-17 — риск снят. Не
блокирующие, оставлены как техдолг/открытые вопросы для реализации: синхронизация портфельных
таблиц B-22 §1.1/B-23 §1.3 с двумя новыми foundation-пакетами (`corex/composer-verify-plugin`,
`corex/ui-kit`) — C-05 §9/C-06 §9, следующая плановая ревизия портфельных таблиц вне волны;
`uncachedSideEffects()`/`component_epilog`-аналог (C-03 §9 п.8) — при первом реальном требовании,
вместе с ревизией B-10 §3.5; D-01 (permission-грамматика, C-01 §9 п.1); IdStrategy-enum шире канона
B-03; параллельная волна W2 (B-27+ не существует на момент закрытия); `RecordsRegistrar::
hasClaimed()`; RegHub §9 (hard-revoke vs grace); `LicenseGate`↔`ModuleActivationGate` переключение
для boxed-Enterprise; `free_domains`-конфиг локация; формат offline-архива; владелец `corex/
composer-verify-plugin`/`corex/ui-kit` (governance, вне волны); per-сайт лицензирование;
boxed→Cloud FlexStore миграция; мультисайт-лимит как `root_plans.limits`.
**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: vault-handoff волны (ADR/Architecture-доки) — вне волны, отдельное решение пользователя
  (Scope Excluded P7.3); Dart-клиент дистрибуции — вне волны (D2); ревизия текста B-11 §3.3 —
  эскалация вне волны; техдолг вне волны: auth не в root tooling (D-05), IdStrategy-enum шире канона
  (D-03), Data/Dto унификация в живом flexcrm-коде (D-06), autoload-dev.psr-4 для crm/tasks,
  layer-map CI-сканер (B-22 §9); маппинг dotted-событий → Workflow (C-02 §9 п.3); JSON Schema
  draft-версия `UiSchema` (C-03 §9 п.5); переключение LicenseGate↔ModuleActivationGate → B-23 §9-Q6;
  www/поддомен-флаги домен-нормализации; калибровка часового порога; формат offline-архива;
  InstallModuleJob queue/retry; пост-upgrade smoke-check модулей; boxed→Cloud FlexStore
  data-миграция; per-сайт лицензирование; владельцы `corex/ui-kit`/`corex/composer-verify-plugin`;
  синхронизация B-22 §1.1/B-23 §1.3 с двумя foundation-пакетами (C-05 §9 п.5, C-06 §9 п.6, D-007
  п.3); `uncachedSideEffects()`/`component_epilog`-аналог (C-03 §9 п.8, D-007 п.1); `TemplateResolver`-
  сигнатура не процитирована в C-03 §4.1 (C-99 §6 п.4, не блокирует); 4 оставшиеся мелочи C-99 §6
  (CLI-нейминг 3 паттерна, `lic_licenses.status='grace'` перегружен, `ProductBlock`-пример в C-06
  путает `Data/`/`Http/Resources/`).
- open_questions: C-02 §9 п.5 (конвенция `Extend/`-файлов); R-32 §8 п.1 (PSX Schema URL не
  подтверждён RAG); C-06 §9 п.3 (распространяется ли C-03 override-цепочка на commerce-сущности —
  продуктовое решение, не архитектурный блокер); C-06 §9 п.5 (мультисайт-лимит как
  `root_plans.limits`) — решение продукт-оунера, не архитектурный блокер.
