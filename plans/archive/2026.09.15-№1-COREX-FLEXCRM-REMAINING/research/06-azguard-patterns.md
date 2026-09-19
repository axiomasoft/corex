# AzGuard: проверенные паттерны и границы переноса

RAG:— Локальное source/test исследование; не внешнее подтверждение CoreX.

Дата исследования: 2026-09-15. Это read-only исследование рабочего дерева
`/home/vostrikov/projects/packages/azguard`, HEAD
`67ebcde7b3ab32aa0205deb37a483b605e140cdb`. Дерево изначально dirty; HEAD сам по себе
не удостоверяет прочитанные рабочие байты. Их SHA-256 и первоначальный статус находятся в
`artifacts/astra-whole-plan-review/azguard-inputs.initial.json` и
`artifacts/astra-whole-plan-review/azguard-status.initial.txt`. Диапазоны чтения —
`artifacts/astra-whole-plan-review/read-pages.jsonl`. Тесты ниже **прочитаны, не запускались**.
Их наличие не означает runtime, Redis, Octane, installed или release acceptance.

## Три разных значения panel

| Понятие | Проверенное назначение | Применение в плане |
|:--|:--|:--|
| AzGuard panel | Namespace разрешений, набор enum/catalog sources и явный текущий контекст | Источник сравнительных приёмов identity/DI; не новое хранилище ролей CoreX |
| CoreX headless panel | Descriptor доступной сущности/вложения/возможности через существующие механизмы | P4.1: метаданные + актуальное пересечение access policies; никакой UI-навигации или выдачи прав |
| Filament panel/plugin | UI-приложение, resources/pages и UI lifecycle | Вне headless CoreX; возможный независимый consumer, не обязательная зависимость |

Различие явно описано в `docs/advanced/panels.md` и видно по отдельному пакету
`packages/filament`. Типизированное имя панели не доказывает доступ к объекту или account.

## Source/test observations

Все пути таблицы относительно AzGuard. Это локальные наблюдения, не выводы Perplexity.

| Область | Реализация / документ | Проверенный тест и предел доказательства |
|:--|:--|:--|
| Типизированная identity | `packages/core/src/Panels/Panel.php`, `Permissions/PermissionKey.php`, `Panels/PanelResolver.php` | `tests/Feature/PanelEnumIdentityTest.php`: enum проходит через identity/grants; нормализация строки не authentication |
| Регистрация и DI | `packages/core/src/Panels/PanelProvider.php`, `AzGuardManager.php`, `AzGuardServiceProvider.php` | `tests/Feature/CustomGrantSourceTest.php`: tagged источник наблюдаем после сброса scoped instances; это не доказательство всех container lifetimes |
| Временный контекст | `packages/context/src/ContextGuard.php`, `AzGuardContextServiceProvider.php` | `tests/Feature/Context/ContextMergeStrategySwapTest.php`: замена стратегии достигает PermissionLayer; ContextGuard восстанавливает прежний контекст в `finally` и очищает request cache |
| Worker lifecycle | `packages/core/src/AzGuardServiceProvider.php` | `tests/Unit/OctaneScopingTest.php`, `Feature/JobProcessingPanelResetTest.php`: симуляция request flush; database job сбрасывает panel, sync job сохраняет вложенный контекст; actual Octane не запускался |
| Границы panel scope | `packages/core/src/Guard/Authorizer.php`, `Registry/Resolver/EffectivePermissionResolver.php` | `tests/Feature/ScopedRolePanelIsolationTest.php`: отдельные панели изолированы, но `null` в некоторых чтениях означает любую панель ради совместимости |
| Кэш | `packages/core/src/Registry/Resolver/PermissionCache.php` | `tests/Feature/PermissionCacheCrossProcessRaceTest.php`: есть Redis multiprocess race fixture с изолированным префиксом и skip-условиями; существование теста не результат Redis-прогона |
| API freeze | `docs/introduction/versioning.md` | `tests/Unit/ApiBoundaryTest.php`: public @api/core snapshot, отсутствие internal types, reflection signatures и имена параметров; не весь satellite API и не все поведенческие defaults |
| DX/scaffolding | `packages/core/src/Commands/MakeGuardPanelCommand.php`, `docs/introduction/headless-quick-start.md` | `tests/Feature/MakeGuardPanelCommandTest.php`: детерминированные stubs и отказ перезаписи без force; config insertion best-effort, не универсальный AST editor |

Пути `Permissions/*`, `Panels/*`, `Registry/*` в первой колонке реализации продолжают
префикс `packages/core/src/`, если он дан перед ними. Физические имена и hashes следует
сверять с inventory, а не выводить из сокращённой записи.

## Что переносим как приём

1. **Явные identity и contribution через DI.** В P4.1/P8 descriptor имеет стабильный
   типизированный ID; видимое название не ID. Contribution подключается существующим
   provider/EntityRegistry/восемью extenders. Проверка duplicate ID у CoreX остаётся явной.
   Нужны два нейтральных consumers, absent/disabled provider и неизвестная сущность.
2. **Контекст на вызов и корректное завершение lifecycle.** P2/P3/P4/P8 не хранят
   actor/account-specific authority в singleton. При временной смене контекст восстанавливается
   и при исключении; tests A→B→A и job/request boundary должны проверять наблюдаемое отсутствие
   утечки. Stateless services не переводятся в scoped автоматически.
3. **Расширяемость через реально используемый порт.** Проверка подмены DI обязана пройти
   реальный hot path, а не только `container->make(Contract)`. Optional auth остаётся A09;
   P9 проверяет public install без private пакетов и отдельный opted-in installed adapter.
4. **Диагностика объясняет решение, но не создаёт authority.** P8 `why` читает существующую
   метаинформацию и объясняет отсутствие/deny. Сам факт получения descriptor или explain
   не заменяет свежую policy/final-write проверку.
5. **API drift виден review.** P2/P9 сравнивают точные signatures, DTO/enum/error semantics,
   имена параметров и defaults с принятым baseline. Snapshot не перегенерируется ради PASS.
   CoreX сохраняет собственный более строгий major gate; AzGuard 0.x policy не импортируется.
6. **Короткий headless quick-start с отрицательным примером.** P4/P8/P9 docs показывают
   минимальную установку, явную регистрацию, policy denial и optional absence. Новый
   scaffolder не нужен без конкретного требования; автоматическая regex-правка config
   не становится частью CoreX scope.

## Что отвергаем и почему

| Наблюдение в источнике | Почему нельзя перенести как гарантию CoreX | Owning проверка |
|:--|:--|:--|
| `AzGuardManager` записывает panels по ключу без duplicate guard | Последняя запись скрывает конфликт; deterministic headless catalogue должен отклонять конфликт | P4.1 duplicate/provider-order cases; P8 compiled catalogue |
| `PanelResolver` допускает config/`app` fallback; strictness зависит от наличия registry | Нельзя вывести blanket fail-closed из quick-start prose | P4.1 explicit identity, unknown/absent denies; P3 actual account resolver |
| `Authorizer::check` возвращает true либо null | null означает продолжение Laravel Gate pipeline, а не окончательный deny | P2 optional auth и P9 actual positive/negative policy integration |
| Глобальный wildcard выходит из EffectivePermissionResolver до Context PermissionLayer и фильтра catalog | `DenyWithoutContextStrategy` не является универсальным ограничителем такого wildcard | P2/P3/P8 current trusted context/final enforcement; не новый wildcard/role engine |
| Tagged GrantSources объединяются по priority | Priority не означает deny precedence; exception skipping зависит от config | P4/P8 explicit policy intersection, deny precedence задан CoreX contract |
| `null` scope иногда значит any-panel | При физическом account isolation отсутствие context не может означать все accounts | P3/P4/P8 missing/forged context negatives |
| In-process PermissionCache читается до durable epoch; fallback cache без LockProvider предупреждает о race | Это не synchronously current revocation fence и не доказательство cross-DB identity | P3 current durable pair/lease/final SQL fence; P10 actual timing |
| Comment AuthorizationContextManager говорит singleton, provider делает scoped binding | Комментарий не является доказательством actual lifetime | P2 реальные bindings и hot-path substitution tests |
| Gate bootstrap использует concrete EffectivePermissionResolver | Подмена интерфейса не гарантирует подмену каждого прямого concrete вызова | P2/P9 end-to-end adapter contract conformance |
| CompositePermissionCatalog строится один раз, dedup допускает metadata replacement | Новые contributions после build требуют явной invalidation; не строгая collision policy | P4/P8 frozen catalogue/activation epoch and conflict checks |
| Filament plugin пишет global config и статически меняет UI policy behaviour | Это UI lifecycle с глобальными эффектами; не reusable headless authorization | UI остаётся внешним; P4/P8 без Filament dependency |
| Документ known-limitations отстаёт от Redis race test | Документ описывает прошлое состояние, а наличие нового теста всё равно не PASS | Source/test/runtime evidence разделены в P1/P9 |

## Совместимость, alternatives и cross-phase impact

Прочитанные `packages/core/composer.json` и `packages/context/composer.json` объявляют
PHP ^8.3 и Illuminate 11/12/13; UI package — Filament ^5.0. Это **declared constraints**.
CoreX auth остаётся на собственной объявленной матрице Laravel 12, пока P9 не квалифицирует
и не принимает её точное изменение. Лицензия root composer — MIT; access/immutable channel
и license actual installed distribution проверяет G-PRIVATE/G-DEPENDENCY отдельно.

Альтернативы: копировать role writer/manager (отклонено A09), сделать Filament обязательным
(отклонено headless/L0), построить новый discovery engine (отклонено A04), либо сохранить
существующие порты и усилить конкретные негативные lifecycle/DI tests (выбрано).
Ни одна находка здесь не разрешает менять AzGuard или product код в design-аудите.

Внешнее сопоставление и независимые origins — [07-open-source-patterns.md](07-open-source-patterns.md).
AzGuard и Filament corroborate identity/registration/lifecycle distinctions; Laravel/OWASP —
механику scoped DI и свежую authorization; Flysystem/tenancy — отличие resource location от
authority. Число CoreX интерфейсов, placement и SQL fencing — локальное design решение.

P2 фиксирует exact API/DI/adoption; P3 сохраняет durable authority; P4/P8 используют headless
descriptors; P9 проверяет отдельный optional auth и actual installed graph; P10 effective
revocation и P11 accountable consumer receipt остаются отдельными результатами. Source-reading
этого документа не закрывает ни один execution item или gate.

## Как потребитель подключает panel

По прочитанному headless quick-start потребитель реализует AzGuardUser, регистрирует
свой PanelProvider в `config/az-guard.php` (`panels`), перечисляет permission enums и
явно выбирает panel при grant/check. UI для этого не нужен. Минимальный пример provider
(документационный, не новый файл CoreX и не результат исполнения):

```php
<?php

declare(strict_types=1);

namespace App\Guards;

use AzGuard\Panels\Panel;
use AzGuard\Panels\PanelProvider;

final class LabPanelProvider extends PanelProvider
{
    #[\Override]
    public function panel(Panel $panel): Panel
    {
        return $panel->id('lab')->permissionEnums([LabPermission::class]);
    }
}
```

`LabPermission` — собственный backed enum потребителя, например ключ `sample.read`.
Panel::id принимает **string**; если используется отдельный enum идентичности панели,
сюда передаётся его value. Типизированные enums разрешений не означают, что каждый
builder method принимает enum. Grant writer и реальные allow/deny checks остаются
AzGuard/consumer-owned; CoreX descriptor сообщает capability и не выдаёт это разрешение.
Default/fallback/null/wildcard ограничения предыдущего раздела обязательно учитываются.
