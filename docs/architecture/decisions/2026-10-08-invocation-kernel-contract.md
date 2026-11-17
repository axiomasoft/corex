---
id: DR-2026-10-08-invocation
date: 2026-10-08
status: superseded
supersedes: [D32 (только для шести алиасов ниже)]
source: git commit fb9acbe6826be0f1786bea5da4efa5a4a605fe43
---

# Контракт обработчика операций переезжает в ядро (`CoreX\Invocation`)

> Обновление 2026-11-17: владелец разрешил несовместимый редизайн до первого использования.
> Алиасы удалены; используются только канонические контракты ядра. Описанное ниже
> исключение для `class_alias` — историческое решение, больше не действующее.


**Решение владельца (2026-10-08):** «Implement the operations hybrid: move the 4 types into the kernel, keep BC via class_alias (deprecated)».

Перенос и закрытый список из шести алиасов повторно приняты при
ревью ветки (historical record in Git) по текущему
поручению владельца принять, доработать или откатить изменения. Запись бота о
предыдущих согласованиях сама по себе не служит полномочием.

## Что меняется

| Старое имя (deprecated, удаляется в 2.0) | Новое имя (ядро `axiomasoft/corex`) |
|---|---|
| `CoreX\Operations\Definition\Contracts\OperationHandler` | `CoreX\Invocation\Contracts\OperationHandler` |
| `CoreX\Operations\Invocation\Contracts\RequestContext` | `CoreX\Invocation\Contracts\RequestContext` |
| `CoreX\Operations\Authorization\Contracts\Principal` | `CoreX\Context\Contracts\Principal` (см. «Единый Principal») |
| `CoreX\Access\Contracts\Principal` | `CoreX\Context\Contracts\Principal` |
| `CoreX\Access\Enums\PrincipalType` | `CoreX\Context\Enums\PrincipalType` |
| `CoreX\Operations\Invocation\Data\InvocationMetadata` | `CoreX\Invocation\Data\InvocationMetadata` |

- Старые имена — `class_alias` (тот же тип, не подтип) в
  `packages/operations/src/Invocation/Internal/deprecated-aliases.php` и
  `packages/access/src/Internal/deprecated-aliases.php`, загружаются через composer `autoload.files`.
- `flex/crm` больше не требует `axiomasoft/corex-operations` в prod (`require-dev` + `suggest`); ребро
  `flex/crm → axiomasoft/corex-operations` запрещено в `scripts/package-graph-gate.json`.
- `tools` и `webhooks` остаются расширениями operations (законные зависимые), новый ранг платформы не вводится.

## Единый Principal

До ветки в репо было два понятия «аутентифицированный субъект»: `CoreX\Access\Contracts\Principal`
(`principalType(): PrincipalType`, `principalId(): string`; реализует `PrincipalIdentity`, на нём
работают AccessChecker, ScopeResolver, grant-lookup и access-azguard) и
`CoreX\Operations\Authorization\Contracts\Principal` (`id(): string`; реализуют только хостовые
`PrincipalResolver` и тестовые fixtures). Первая версия переноса сделала второе ядерным типом
(`CoreX\Invocation\Contracts\Principal`), и в ядре появился дубль. Исправлено так:

- В ядре одно понятие: `CoreX\Context\Contracts\Principal` (форма access: тип + id) и
  `CoreX\Context\Enums\PrincipalType` (`user`, `agent`, `api_key`). Модуль Context уже владеет
  «кто и где исполняется» (TenantContext, impersonation, `IdentityPasswordRehasher`), Invocation от него
  зависит законно (зона TenancyContracts).
- `RequestContext::principal()` возвращает этот тип. `CoreX\Invocation\Contracts\Principal` удалён
  (он не существовал на базе и нигде не был опубликован).
- Имена access — deprecated alias того же типа: методы совпадают, BC полный, `PrincipalIdentity`
  сразу годится как caller операции без адаптера.
- Старое имя operations тоже alias ядерного типа, но метод `id()` заменён на `principalId()`
  (плюс `principalType()`). Это единственная поломка: её нельзя избежать при слиянии двух форм,
  и она допустима, потому что пакет не опубликован.

Почему форма access, а не `id()`:
- identity в платформе — пара (type, id): grants, morph-тип `PrincipalIdentity`, DB-ограничения
  access. Один `id()` неоднозначен между user/agent/api_key.
- у формы access больше реализаций и потребителей (access, access-azguard, seeders, pg-тесты);
  у `id()` в репо только тестовые fixtures.

Отвергнутые варианты:
- (1) Ядерный `Principal { id() }`, который `extends` access-интерфейс. Остаются два типа и
  неоднозначный `id()`; параметры `AccessChecker` пришлось бы расширять, ломая LSP у реализаторов.
- (2) `RequestContext` без principal (только `principalId(): string`). Handler теряет тип субъекта,
  а понятие «субъект» в ядре всё равно нужно access.
- (3) Ядро использует `Audit\Enums\ActorType` вместо `PrincipalType`. Отвергнуто: actor включает
  `system` и `support`, которые не могут держать роли. `PrincipalType` — строгое подмножество
  значений `ActorType` (это записано в docblock), но это разные понятия: actor аудита против
  аутентифицированного субъекта.

## Почему `class_alias`, а не `extends`

`RequestContext` стоит в позиции параметра `handle()`. Старое имя как подынтерфейс нового сузило бы параметр у
34 существующих обработчиков (нарушение LSP — фатальная ошибка PHP). Только alias сохраняет и старые, и новые
сигнатуры совместимыми.

## Отступление от D32

D32 запрещает `class_alias`, потому что Roave/BetterReflection и PHPStan не дают по нему полного BC-сигнала.
Для этих типов (4 типа контракта handler'а и 2 типа principal из access) D32 сознательно отменён владельцем. Последствия:

- Roave видел старые классы как «deleted», а сигнатуры — как «non-contravariant» (ложноположительно: в
  рантайме это один тип, см. `DeprecatedInvocationAliasesTest` и `DeprecatedPrincipalAliasesTest`). Пока
  bc-check существовал, эти строки были одобрены поимённо; 2026-10-08 владелец удалил bc-check целиком.
- PHPStan не выдаёт предупреждение deprecation по alias; сигнал о депрекации — CHANGELOG operations/access и этот файл.
- Новые alias вне этого списка по-прежнему запрещены D32.
