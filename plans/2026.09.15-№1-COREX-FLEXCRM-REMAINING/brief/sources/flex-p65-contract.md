# P6.5 — brief нового пакетного делегата B-33/B-34

> **Актуализация D42 / 2026-09-13:** делегат ниже уже существует.
> Next — waiting: новые owning DI/AP receipts и scoped D2 release; повторный
> design только с новым evidence, не создание второго плана. Актуальные pins и scoped progress: `P6.5-delegate-progress.md`.
> Историческая сверка ниже сохраняется; D2 не released for implementation.

## Назначение и границы

Это исходный design input, не acceptance выполненного пакета. Отдельная
сессия работает в `/home/vostrikov/projects/packages/corex` с уже созданным
планом `2026.09.13-№1-COREX-TENANCY-IDENTITY`. Новый plan ID не выбирается;
mapping к FLEX-2 P6.5 сохраняется.
Оригинальные terminal plans не реопеняются. Ни код, ни внешние операции в
текущем FLEX-2 design не выполняются.

## Репозиторная сверка — 2026-09-13, RAG:—

- P6.1–P6.4 имеют GREEN state; P6.4 acceptance на историческом `82abc18` остаётся
  фактом закрытия и не объявляется текущим HEAD CoreX.
- Активный `plans/COREX-CORE` отсутствует. Текущий CoreX HEAD — `5c936df7a832e6c64a8022e5e03f37a97dd2c9b1`.
  Дерево dirty: source hashes ниже описывают read-only candidate snapshot, а не
  committed/pristine/runner GREEN; будущий оператор переснимает их.
- COREX-SUCCESSOR terminal; D11–D21 приняли branch choices для будущих specs,
  не runtime delivery. COREX-APPROVED-SPECS проектирует normative corrections,
  template integrity, consumer bindings и distribution/pristine proofs.
  Это самостоятельный scope, не замена всех B-33/B-34 outputs.
- Точный поиск TenantStorage/signup_key/MembershipService/SwitcherService/
  MembershipGranted/membership_version в обоих новых планах и src core/tenancy
  не дал совпадений. Это сигнал для inventory, а не доказательство отсутствия
  эквивалентной реализации; проверка символов/тестов — первая работа делегата.

| Provenance source | SHA-256 candidate bytes |
|:--|:--|
| `/home/vostrikov/projects/packages/corex/plans/2026.09.12-№1-COREX-SUCCESSOR/handoff.md` | `fa2ab4aea523bfecc620452ae91629b25dc49cbbe52b1e09773b555524d65171` |
| `/home/vostrikov/projects/packages/corex/plans/2026.09.12-№2-COREX-APPROVED-SPECS/plan.md` | `d452316fe20fa7ba9fddb2f68e571443c1ec285604aba52a2b1d1fece9ec89e2` |
| `/home/vostrikov/projects/packages/corex/plans/2026.09.12-№2-COREX-APPROVED-SPECS/handoff.md` | `cda575c9cd2f1205c4550e922b6451328c5cf7c7a4c317a09be5fcd0152540a7` |

Иностранные paths — provenance; execution input FLEX-2 — этот локальный файл.

## Источники и порядок Required Reads целевого design

1. Этот brief и локальная D41: `/home/vostrikov/projects/flexcrm/plans/FLEX-2/decisions/D41-p6-next-slice.md`.
2. Активные CoreX instructions, COREX-APPROVED-SPECS plan/decisions D1/D2,
   COREX-SUCCESSOR owner records D11–D21; source hashes переснимаются там.
3. `/home/vostrikov/projects/flexcrm/plans/flex/30_blueprints/B-33_provisioning-multidb.md`:
   §2.4, §3.2, §3.9, §4 и §7.
4. `/home/vostrikov/projects/flexcrm/plans/flex/30_blueprints/B-34_cross-db-identity.md`:
   §3.2–§3.5, §4.1–§4.3, §7 и §8.4–§8.5.
5. FLEX-2 D23 (Q64 → Н1–Н4), D28 (central IdP owner), D38/D40 и
   schema/approval.md. Старые blueprint signup/invitation flows подчиняются D40:
   CRM не пишет account/access membership, не публикует onboarding endpoints.

## Матрица требуемых outputs

| Seam | Пакетная часть P6.5 | Другой владелец / gate | Negative acceptance |
|:--|:--|:--|:--|
| Storage | TenantStorage contract и owning tenant adapter; tenant_id только из TenantContext | Реальный S3/provider/backup/move — P6.25 | A→B path, traversal, local↔fake S3 driver, отсутствие raw Storage обхода |
| Provisioning idempotency | signup_key seam; существующий root_accounts.data и approved expression-unique index при доказанной необходимости | Paid provisioner, orchestration/компенсации полной saga — P6.25; любой новый индекс требует exact DDL approval | Повторный и concurrent key не создают второй account; retry/compensation replay contract |
| Identity safety | Н1 UUID root identity; Н2 нет auto-merge; Н3 phone без unique; Н4 email транспорт | Central create/grant/accept/revoke writer — P6.26 | Same email/phone не связывают identities; чужой account/acting actor отвергается |
| Membership transport/events | CentralApiClient и event/DTO contracts; owning local projection/session adapter | Central API и privileges — P6.26; permission wsp.member.remove проверяется owning policy | Cross-account revoke, duplicate event/replay, revoke→session deny ≤60 с; UUID-only payload |
| Switcher | Read/cache seam; central Redis switcher:version:{identity_id}, versioned list и fallback | Central accounts endpoint, authoritative membership/SSO check — P6.26 | Redis loss/regrant не возвращает revoked account; TTL меню не разрешает SSO |
| MCP maps | Карты tenancy.*/identity.*/storage.* и ownership для готовых контрактов | Public/external tools и central handlers — owning consumer, не автоматическая публикация | Tools не обходят policy/tenant scope; отсутствующая реализация помечена pending |

## Обязательный результат следующего design

Inventory даёт на каждый row: existing symbol/test/source SHA либо конкретный
missing output; qualified owning package/consumer и dependencies. Detailed
items имеют Intent/Scope/Inputs/Required Reads/Files/Validation/Deliverables,
Routing Batch/Review и Phase Assurance. Каждый непустой authority/input gate
записан до dispatch; готовые docs/inventory subsets не зависят от публикации.

Контракты не тянут Stancl вне owning tenancy adapter. Мутации — Actions или
согласованный observer, Services — evaluator/read/transport. Бизнес-решения
billing, central grants, DNS/TLS и provisioner не переносятся в L0. Public
Contracts/major, immutable private source, dependency/ref changes и release
имеют scoped gates действующих CoreX decisions. Boxed unsupported/deferred
workspaces не превращаются в готовый multi-workspace adapter.

Утверждённая FlexCRM схема: version 0.2.2, SHA-256
`1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f`.
Нулевая новая таблица не освобождает expression-index от schema delta approval.
Новый design не разрешает DDL, production operations или release.

## Будущая приёмка milestone

После реализации отдельная FLEX-2 reconciliation пишет
`findings/P6.5-corex-package-acceptance.md`: actual delegate ID/source ref,
terminal result и deviations, mapping rows → symbols/test refs, exact quality
commands/environment receipts на одном source ref, release refs/CHANGELOG и
remaining central boundaries. Старые test counts и новые design outputs не
заменяют GREEN acceptance. Требования D14 об audit/release квалифицируются
целевым планом; эта сессия audit consent не создаёт.

## Дальнейшая программа

P6.5 package acceptance → workflow/wakeup P6.6, attributes P6.7, import/export
P6.8 и нужная infrastructure P6.9 → core CRM P6.10 и app composition P6.18.
P9–P13 — готовый тестовый auth/profile/Tasks-контур; P6.17/P6.18 не закрыты им.
Documents, Tasks collaboration и Calendar получают отдельные acceptance
срезы. Central/cloud остаются P6.25/P6.26, отрасли и production отдельно.
