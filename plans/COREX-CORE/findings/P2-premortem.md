# FINDINGS — P2 Pre-mortem (Klein prospective hindsight)

**Дата:** 2026-07-12 · **Прогон:** workflow `wf_cc73752d-89b`, 3 независимые лензы (opus/high),
18 причин → кластеризация Fable. **Триггер:** high-stakes (tenant isolation + незарелиженная
зависимость + auth), plan-protocol §7. Постановка: «+9 мес, P2 в проде, провал — почему».

Митигации со статусом **[внесено]** интегрированы в поля items 2026-07-12; **[OQ-n]** — вынесены
в open-questions.md; **[след. фаза]** — за пределами P2, наблюдение.

## Ленза A — изоляция тенантов

| # | Причина | Механизм | Митигация | Owner |
|:--|:--|:--|:--|:--|
| A1 | Queue-воркер наследует чужой tenant-контекст | Джоба без tenant-payload на long-lived Horizon-воркере исполняется в контексте предыдущей джобы аккаунта A | AC «leaked worker state»: джоба без tenant-tag после тенант-джобы обязана исполниться в central; QueueBootstrapper делает `end()` в finally безусловно **[внесено P2.5]** | P2.5 |
| A2 | AzGuard capability-кеш не под tenant-префиксом | RoleMatrixCompiler пишет в тенант-БД корректно, но кеш прав AzGuard может жить в общем store → права A отдаются B | Implementation Rules: verify что AzGuard-кеш под prefix-mode; иначе tenant-salt в ключ; AC параллельный compile двух тенантов **[внесено P2.8]** | P2.8 |
| A3 | Golden-шаблон дрейфанул от migration-head | Клон без миграций (R-11 п.5): CI-сборка шаблона отстала → новые аккаунты без свежих (в т.ч. security) констрейнтов | Template-parity gate: шаблон `ready` только при diff(pg_dump-schema шаблона, миграции на пустой БД)=0 + schema-hash в root_templates; провижининг отказывает не-ready **[внесено P2.3/P2.12, D24]** | P2.3/P2.12 |
| A4 | Root-миграция ушла в тенант (или наоборот) | Широкий path-glob раннера подхватывает чужой слой | Arch-тест «migration-path partition»: root\|tenant по директориям, множества дизъюнктны, раннеры грузят только свой слой **[внесено P2.13]** | P2.13 |
| A5 | workspace-scope обойдён raw-запросом / модель без scope | DB::table()/Scout мимо Eloquent global scope; модель с capability workspace_scoped без WorkspaceScope | AC «scope coverage»: множество workspace_scoped из EntityRegistry ≡ модели с зарегистрированным WorkspaceScope (reflection/arch) **[внесено P2.9]**; raw-обход — ловит P1.13-класс правил частично, остальное — ревью | P2.9/P2.13 |
| A6 | Гонка активации pending-болванки | Заём и pending→active не в одной транзакции → host резолвится в чужую БД | Инвариант: заём (SKIP LOCKED) + переход status+slug+owner — ОДНА транзакция; slug не выставляется до фиксации owner; AC concurrent-активация **[внесено P2.4/P2.12]** | P2.4/P2.12 |

## Ленза B — зависимость от stancl v4 (dev-master)

| # | Причина | Механизм | Митигация | Owner |
|:--|:--|:--|:--|:--|
| B1 | Пин sha протух (force-push/rebase master) | Чистый CI `composer install` падает — sha недостижим из ветки | Supply-chain якорь: зеркалировать пин-коммит в собственный форк, composer тянет из форка **[внесено P2.1, D25]** | P2.1 |
| B2 | Тег 4.0.0 вышел несовместимым с замороженным sha | Namespace-реорг/сигнатуры между sha и тегом → все override переписывать | Снапшот extension-поверхности stancl (какие классы/интерфейсы/конфиг-ключи наследуем) как deliverable P2.1; изменения поверхности = сигнал эскалации **[внесено P2.1/P2.3/P2.5]** | P2.1 |
| B3 | PoC зелёный, прод красный: `CREATE DATABASE … TEMPLATE` под конкурентным доступом | PG запрещает клон при активной сессии к шаблон-БД; PoC мерил только холодный happy-path | Сценарий clone-under-concurrent-access + bounded-retry в PoC; карантин шаблона (никто не коннектится к golden) **[внесено P2.1/P2.3]** | P2.1/P2.3 |
| B4 | jobpipeline rc-дрейф тихо вешает провижининг | stancl master тянет нестрогий констрейнт jobpipeline; rc8 меняет сериализацию | Точный пин `2.0.0-rc7` в КОРНЕВОМ composer.json (перебивает транзитивный); PoC-сценарий 5 и на locked, и на fresh-update; FSM-путь provisioning→failed **[внесено P2.1/P2.12]** | P2.1 |
| B5 | FPM упирается в перф-стену, Octane запрещён | «init <5мс p95» мерили на idle-стенде с 2 тенантами; прод 1000+ тенантов + PS-rebind в transaction-mode | Сценарий 7 на прод-репрезентативном числе тенантов (1000+) с EMULATE_PREPARES; НФТ-гейт RPS/ядро — не достигнут → эскалация на гейте, не в проде **[внесено P2.1]** | P2.1 |
| B6 | Поздний NO-GO (P2.10/P2.12), fallback F1/F2 непроверен | Контрактная граница протекла extends'ами; escape-hatch предположён, не доказан | Deliverable «fallback viability»: тонкая F2-спайка (conn-switch+cache-prefix+queue-payload) доказывает выполнимость контрактов без stancl; re-GO checkpoint после P2.9 **[внесено P2.1]** | P2.1 |

## Ленза C — auth/RBAC/identity

| # | Причина | Механизм | Митигация | Owner |
|:--|:--|:--|:--|:--|
| C1 | RoleMatrixCompiler разъехался с версией AzGuard | soft-ref без контрактного теста; тесты на стабах | AC round-trip: compile пишет ключ → РЕАЛЬНЫЙ AzGuard `hasPermission` его читает; сверка типа `azguard_role_id`↔azguard.roles.id до кода (снять C6 [UNVERIFIED]); пин azguard **[внесено P2.8]** | P2.8 |
| C2 | dept_tree-предикат неверен → privilege escalation | Метка с дефисами/NULL-department/agent с own | Property-AC монотонности scope (N ⊇ N−1); `userDeptPath=NULL → WHERE false`; agent/api_key own/dept → `WHERE false` ENFORCE (не warning); нормализация hex-метки покрыта тестом **[внесено P2.9]** | P2.9 |
| C3 | Денормализация users разъехалась: revoke не доехал → сохранённый доступ | fanOut insert-if-missing + failed-бэклог без свипа | upsert с version-guard (`root.updated_at > users.synced_at`); команда `identities:reconcile`; AC «revoke fail-closed» **[внесено P2.7]** | P2.7 |
| C4 | OIDC и boxed-Fortify дают разные Principal | Два guard-а тестируются порознь; downstream ветвится | AC «boxed↔cloud parity»: один Principal, идентичный AccessChecker/ScopeResolver-тест под обоими драйверами; сверка ADR-017 — DoR-гейт до старта P2.10 **[внесено P2.10, OQ-7]** | P2.10 |
| C5 | Impersonation неразличима в аудите | actorType enum P1.8 без 'support'; зеркало в root теряется | Повышено из Completion-Notes-вопроса до решения ДО P2.11: actorType расширить (см. OQ-7); AC dual-log атомарно; `used_at` при start (не при stop) **[внесено P2.11, OQ-7]** | P2.11/P1.8 |
| C6 | IdentitySyncer теряет обновления (гонка fanOut) | Две волны fanOut перегоняют друг друга — применяется не последняя | version/updated_at-guard в upsert; payload несёт source-updated_at; AC конкурентного fanOut **[внесено P2.7]** | P2.7 |

## Кластеры → системные решения

1. **Гейт P2.1 недоспецифицирован против прод-реальности** (B3/B4/B5/B6) → PoC расширен:
   clone-under-lock, 1000+ тенантов, fresh-update-лейн, F2-спайка, снапшот поверхности. Внесено в P2.1.
2. **Кеш/очереди — главный вектор межтенантных утечек** (A1/A2) → AC-инварианты в P2.5/P2.8.
3. **Целостность парка = целостность шаблона + путей миграций** (A3/A4) → template-parity gate (D24) + arch-partition (P2.13).
4. **Supply-chain незарелиженной зависимости** (B1) → форк-якорь (D25).
5. **Identity-синк требует ordering-гарантий** (C3/C6) → version-guard + reconcile-свип (P2.7).
