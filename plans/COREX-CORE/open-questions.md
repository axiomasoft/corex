# OPEN QUESTIONS — COREX-CORE

Протокол plan-protocol §3: спорный/незакрытый пункт НЕ висит в теле фазы, а вынесен сюда
со статусом `Exploration` / `Decision pending (owner needed)` / `Resolved → D#`.
Файл — plan-wide; сгруппировано по фазам. OQ-5..9 заведены max-design-пассом 2026-07-12
(RAG/аудит/pre-mortem — findings/).

## Phase P1

### OQ-1 — `updated_at` в append-only `sys_audit_log` (P1.8)

- **Статус:** Resolved → DB_SCHEMA §Конфликт-2 (владелец, 2026-07-13)
- **Суть:** Контракт аудита append-only (`AuditLog::UPDATED_AT = null`, B-10 §9 п.7) против
  `DB_SCHEMA §Конфликт-2`, где у таблицы показаны `created_at/updated_at`. Прямое противоречие
  контракта и каталога схемы.
- **Резолюция:** владелец DB_SCHEMA разрешил §Конфликт-2 2026-07-13 (тем же днём, до исполнения
  P1.8): норма «append-only без ≥1 легитимного post-insert поля → без `updated_at`», применение —
  за владеющим пакетом на своём майлстоуне. `sys_audit_log` не имеет легитимных post-insert полей →
  P1.8 создал миграцию БЕЗ `updated_at`, `AuditLog::UPDATED_AT = null` сохранён без конфликта.
  Escalation НЕ потребовалась — детали phases/P1.md P1.8 Completion Notes.
- **Owner:** владелец `~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md` (закрыто).

### OQ-2 — `DomainEvent` ↔ `CoreXEvent` / `Cancellable` (P1.9)

- **Статус:** Resolved → D27 (2026-07-13)
- **Суть:** B-10 задаёт `DomainEvent` (метаданные eventId/occurredAt/tenant); Architecture §7.3 —
  `CoreXEvent` + `Cancellable`; в коде `CoreEvent` — пустая база. Нужна одна согласованная иерархия.
- **Резолюция:** `DomainEvent` — каноническая база (B-10 §3.1 канон); `Cancellable` — отдельный
  опциональный интерфейс (`packages/core/src/Events/Cancellable.php`, не примешан в базу);
  `CoreEvent` удалён (pre-1.0 breaking, D17); `SettingChanged` ретрофитнут, `FeatureFlagToggled`
  создан на `DomainEvent` — см. plan.md D27, phases/P1.md P1.9 Completion Notes.
- **Owner:** исполнитель P1.9 (plan-exec), запись решения — plan.md §5.

### OQ-3 — Морф-хендлы crm/tasks: underscore → точка (P1.2)

- **Статус:** Resolved → D1 (greenfield)
- **Суть:** Перевод прежних underscore-алиасов морф-карты на точечные handles (`crm.deal`) ломает
  морф-зависимые данные.
- **Резолюция:** greenfield — прод-данных нет (D1). P1.2 переводит провайдеры + обновляет
  морф-зависимые тесты. Оставлено для traceability.

### OQ-4 — `SettingChanged` эмитится до появления базы `DomainEvent` (P1.7 раньше P1.9)

- **Статус:** Resolved → interim + ретрофит
- **Суть:** Порядок фазы ставит Settings (P1.7) раньше базы событий (P1.9); `SettingChanged`
  нужен раньше своей базы.
- **Резолюция:** P1.7 эмитит на interim-базе (`CoreEvent`/TODO-шов), P1.9 ретрофитит на
  `DomainEvent`. Не блокирует P1.7. Оставлено для traceability.

### OQ-9 — `Manifest::featureFlags()` типизируется классом из P1.9 (P1.3 раньше P1.9)

- **Статус:** Resolved → D20 (interim-шов, зеркало OQ-4)
- **Суть:** `FeatureFlagDefinition` живёт в corex/core и создаётся P1.9; манифест P1.3 ссылается
  на него раньше (forward-ref, phpstan бы упал).
- **Резолюция:** P1.3 типизирует `featureFlags()` как `list<mixed>` + TODO-ссылка на P1.9;
  P1.9 ретрофитит на `list<FeatureFlagDefinition>`. Аудит F7, D20.

### OQ-12 — Ядро де-факто PG16-only (SQLite-лейн только для pure-PHP)

- **Статус:** Resolved → P1.26 (2026-07-15)
- **Суть:** `sys_*`/`mod_*` DDL PG-нативны (ltree/BRIN/`NULLS NOT DISTINCT`/partial-unique/
  `pg_advisory_xact_lock`, D12); SQLite-лейн (`composer test`) покрывает только pure-PHP items,
  вся data-loss-регрессия — на PG-лейне (`->group('pg')`). Де-факто потребитель ядра обязан
  держать PostgreSQL 16 — но это нигде не объявлено требованием пакета (composer `require`,
  README) как формальный контракт.
- **Резолюция:** вариант (а) — PostgreSQL 16 объявлен требованием ядра явно: `packages/core/README.md`
  и `packages/modules/README.md` несут секцию «Требование к СУБД», `CLAUDE.md` строка про архитектуру
  ядра, оба `CHANGELOG.md` несут секцию «Requires». SQLite-лейн задокументирован как best-effort
  pure-PHP unit-тестирование, не заявленная production-поддержка.
- **Owner:** Dmitry Vostrikov (решение принято в сессии P1.26, 2026-07-15).

### OQ-15 — Что означает тег `v0.1.0` ядра (семантика первого релиза)

- **Статус:** Resolved → D68 (2026-07-15)
- **Суть:** P1.26 гейтит тегирование на «решение OQ-15 (что означает тег v0.1.0)», но OQ-15
  никогда не был заведён в этом файле (фантом — M-4 слепого аудита D68, тот же класс, что
  стоивший ходов OQ-11, D54/D57). Нужна явная семантика: что потребитель вправе ожидать от
  `corex/core v0.1.0` / `flex/modules v0.1.0`.
- **Резолюция (D17):** v0.1.0 = ПЕРВЫЙ тегированный релиз ядра; оба пакета pre-1.0 (0.y.z, MIT) —
  публичный API **НЕ заморожен**, breaking-изменения контрактов **легальны в MINOR** до 1.0
  (SemVer §4); потребители обкатывают API через симлинк/path-repo (D1). CHANGELOG несёт секцию
  `### Deprecated / Will break in 0.2`. 1.0 НЕ тегается, пока EntityRegistry/TenantContext не
  обкатаны потребителями (pre-mortem кластер D). Известные латентные дефекты класса M-1/M-2
  закрываются P1.29 ДО тега (D68).
- **Owner:** Dmitry Vostrikov (закрыто D68).

## Phase P2

### OQ-5 — stancl master требует `php ^8.4`, CoreX держит `^8.3` (блокер пина)

- **Статус:** Decision pending (owner needed — Approval Owner плана)
- **Суть:** RAG:✅ 2026-07-12 (findings/P1-P2-rag-verification-2026-07-12.md §1): raw composer.json
  ветки master archtechx/tenancy = `"php": "^8.4"`; corex монорепо — `php ^8.3`. Composer install
  master-пина (D8) на текущем констрейнте НЕ пройдёт вообще.
- **Варианты:** (а) бамп `php ^8.4` по всему монорепо — breaking для потребителей пакетов,
  требует отдельного D#/ADR + координации с P1; (б) запинить более старый sha master, где
  констрейнт был `^8.3` (git blame composer.json репо tenancy) — жертвуем свежестью v4;
  (в) держать PoC/tenancy-интеграцию в отдельном 8.4-окружении — расщепляет тулчейн.
- **Гейт:** решение — предусловие старта P2.1 (внесено в его Implementation Rules).
- **Owner:** Dmitry Vostrikov.

### OQ-6 — Расхождения DB_SCHEMA: счёт root_ (18 vs 20) и отсутствующая `sys_pubsub_messages`

- **Статус:** Decision pending (owner needed — владелец DB_SCHEMA)
- **Суть:** (1) префикс-карта §2 говорит `root_ = 18`, field-level §3.1 содержит 20 таблиц
  (аудит F18); (2) `sys_pubsub_messages` (driver-local таблица db-poll драйвера P1.11) в DB_SCHEMA
  не описана вовсе (аудит F12) — «полное field-level sys_» и счёт sys_=6 неверны при database-драйвере.
- **Резолюция (путь):** владелец DB_SCHEMA чинит счёт §2 и добавляет примечание про driver-local
  таблицы (вне golden-шаблона). План таблиц НЕ выдумывает (P2.2 создаёт 17; P1.11 — driver-local нота).
- **Owner:** владелец `~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md`.

### OQ-7 — Actor-type `support` (импесонация) отсутствует в AuditEntry CHECK; ADR-017 не сверен

- **Статус:** Decision pending (решить ДО P2.10/P2.11; предложение готово)
- **Суть:** (1) P1.8 задаёт `actor_type CHECK(user/agent/api_key/system)` — действия под
  импесонацией неразличимы в аудите (комплаенс §7.3 п.10, premortem C5); (2) контракт IdP
  ADR-017 [UNVERIFIED] — форма id_token (sub/acct/email/imp) и endpoints не сверены (premortem C4).
- **Предложение:** расширить CHECK до 5 значений (`support`) + `grant_id` в context — если P1.8
  уже закрыт к моменту P2.11, ретро-миграцией; сверку ADR-017 держать DoR-гейтом P2.10 (внесено).
- **Owner:** Dmitry Vostrikov (ADR-017 — владелец IdP-контракта).

### OQ-8 — stancl broadcasting: fix-PR #1448 открыт, не смёржен

- **Статус:** Exploration (наблюдение; scope P2 не блокирует)
- **Суть:** RAG:✅ 2026-07-12: PR #1448 (BroadcastingConfigBootstrapper fix) открыт — баг активен
  в master. План и так исключает config-bootstrapper (P2.5 Scope Excluded, путь BroadcastChannelPrefix).
- **Резолюция (путь):** при появлении broadcasting-потребности (FlexCRM realtime, roadmap P3) —
  проверить статус PR; до тех пор — исключено из scope.
- **Owner:** исполнитель P2.5/будущего realtime-item.
