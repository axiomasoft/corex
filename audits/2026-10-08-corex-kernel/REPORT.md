# Аудит выполненного плана 2026.09.27-№1-COREX-KERNEL

Дата: 2026-10-08. Проверенный HEAD: `4deaa44919ce0d57062ffd5d32c18610ad3fd572`.
Задание: проверить результат всех фаз плана, искать только существенные проблемы среднего и высокого уровня, подготовить исправления для другой модели. Длинные прогоны тестов исключены по указанию владельца.

**Найдены 9 подтверждённых проблем: 5 высокого приоритета P1 и 4 среднего P2.** Формальные структурные гейты проходят, но есть разрывы между компонентами: авторизация полей, исход tool-эффекта, ownership схемы, продолжение recurrence/materialization и admission wakeup. Эти сценарии мешают считать итоговое ядро надёжным. Исправления в product code не внесены.

P1 означает утечку данных, нарушение целостности/исхода эффекта либо потерю обязательной обработки в поддерживаемом сценарии. P2 означает существенный отказ интеграции, восстановления или контроля исполнения. Все пункты — реальные дефекты; стилистические замечания и мелкие недочёты исключены. Сложность исправления указана отдельно от приоритета.

| ID | Приоритет | Сложность | Проблема | Связь с планом |
| --- | --- | --- | --- | --- |
| KRN-01 | P1 | Высокая | Template/module schema теряет ownership; purge оставляет таблицы | P08.1, D111 |
| KRN-02 | P1 | Средняя | Search возвращает поля без их проверки авторизатором | DataScope SPI, P04, P09.14 |
| KRN-03 | P1 | Высокая | Проверяемая write-интеграция tools коммитит эффект при `not-executed` | ToolOutcome, P06.2, P09.11 |
| KRN-04 | P1 | Высокая | Resume recurrence пропускает события и завершается ложным `complete` | Recurrence API, P09.3/4 |
| KRN-05 | P1 | Высокая | Task materializer сохраняет cursor, но каждый раз начинает сначала | TasksWorkflow API, P09.3/4 |
| KRN-06 | P2 | Высокая | Digest due source исполняет работу после отказа проверки версии wakeup | P07.5, D96 |
| KRN-07 | P2 | Средняя | Terminal digest rows вытесняют новые due runs | P07.5, DueWakeupSource SPI |
| KRN-08 | P2 | Средняя | Tasks-workflow не регистрирует собственный wakeup handler | P07.5, D96 §C.9 |
| KRN-09 | P2 | Средняя | Все окна серии используют один wakeup key; `conflict` игнорируется | Scheduling SPI, TasksWorkflow API |

Каждый дефект воспроизведён коротким диагностическим сценарием. Сводные результаты: [reproductions.json](reproductions.json), исполняемый код: [reproduce.php](reproduce.php). Exit 0 диагностики означает успешное воспроизведение наблюдения, а не отсутствие ошибки продукта.

## KRN-01. Предустановка Module-набора обходит учёт владения схемой

**Места:** [LaravelSchemaMigrator.php](../../packages/core/src/Database/Internal/LaravelSchemaMigrator.php):104–118; [DatabaseModuleLifecycle.php](../../packages/core/src/Modules/Internal/Lifecycle/DatabaseModuleLifecycle.php):616–675, 449–498; [schema-policy.md](../../docs/architecture/schema-policy.md):72–77.

**Требование:** [D111](../../plans/archive/2026.09.27-№1-COREX-KERNEL/decisions/D111.json), пункты 5–6: Module-наборы создаёт ModuleLifecycle, template использует SchemaMigrator. В действующей schema-policy и README пакетов прямо разрешён template `corex:schema:migrate --scope=tenant --modules --database=<template>`. Lifecycle обещает автоматические ownership claims и удаление owned schema при purge.

**Сценарий:** Module-набор сначала установлен через `SchemaMigrator::migrate(..., includeModules: true)` — тот же путь, что использует документированный template command. Затем выполняются `enable → disable → uninstall(keepData: false)`.

SchemaMigrator вызывает Laravel Migrator без `withMigrationClaims`. При последующем enable миграции уже присутствуют в журнале, поэтому события `MigrationStarted/Ended` не возникают и ownership не восстанавливается. Purge читает пустой `mod_records` и успешно переводит модуль в `purged`, оставляя его таблицы. Это также затрагивает клонирование подготовленного template: в новой базе уже будут таблицы и migration journal без их module claims.

**Доказательство:** [repro-preinstalled-module.log](repro-preinstalled-module.log): claims `0`, state `purged`, `sba_widgets` существует. Контроль на той же PostgreSQL базе с прямым lifecycle enable: [repro-module-direct-control.log](repro-module-direct-control.log) — claims `2`, после purge таблицы нет. Использованы настоящий migrator, lifecycle и существующие migration fixtures; mock только предоставляет module manifest.

**Последствие:** purge сообщает успех без удаления данных/схемы модуля. Repair/re-enable может столкнуться с уже существующими объектами после сброса journal. Нарушена согласованность схемы, состояния и учёта владения.

**Исправление:** обеспечить единый учёт владения для разрешённого template/bulk пути и lifecycle. Варианты реализации должны сохранять D111: провести Module-наборы через общий механизм lifecycle/claims либо явно сохранять и безопасно переносить доказанное владение при предустановке. Не присваивать модулю все существующие таблицы базы и не выводить ownership только из префикса имени. Уточнить согласованную семантику `migrateSets` для Module-наборов.

**Приёмка:** PostgreSQL: template preparation → clone/enable → purge действительно удаляет только объекты модуля; platform/host tables сохраняются; module journal/claims/state согласованы. Проверить обычный enable, upgrade и repair после purge. Тест разместить в core package рядом с schema/lifecycle integration tests.

## KRN-02. Авторизация Search не получает возвращаемые поля

**Места:** [PolicySearchHydrator.php](../../packages/search/src/Internal/Query/PolicySearchHydrator.php):38–48; [DataScopeAuthorizer.php](../../packages/core/src/Data/Contracts/DataScopeAuthorizer.php):15–24; [PolicySearchHydratorTest.php](../../packages/search/tests/Unit/PolicySearchHydratorTest.php).

**Контракт:** DataScopeAuthorizer принимает конкретный список `fields`. [README Search](../../packages/search/README.md) требует hydration через текущую policy boundary. `SearchSnapshot::resultColumns` — разрешённая форма схемы, а не разрешение текущему пользователю читать каждый столбец.

**Сценарий:** employee schema включает `id`, `name`, `salary`. Текущая политика позволяет читать запись, но запрещает поле `salary`. Hydrator вызывает authorizer с `fields: []`, затем возвращает пересечение current fields с глобальным `resultColumns`, включая зарплату.

**Доказательство:** [repro-search-fields.log](repro-search-fields.log): авторизатор видел только `[[]]`, результат содержит `salary: 100000`. Это настоящий hydrator и штатный CallableDataScopeAuthorizer с политикой, запрещающей запрос поля salary. Текущий unit test проверяет record-level denial и schema allow-list, поэтому не выявляет обход field policy.

**Последствие:** поле, доступное схеме, но запрещённое текущему actor, раскрывается через поиск. Повторная загрузка текущей записи и проверка schema hash этого не предотвращают.

**Исправление:** вычислить реально возвращаемую проекцию до принятия решения и проверить её поля через текущий authorizer. При существующем bool/decision SPI допустимо отклонять всю запись, если хотя бы одно запрошенное поле запрещено. Если нужно частичное раскрытие, проверять поля по согласованному контракту; нельзя предполагать, что пустой список автоматически проверяет все столбцы.

**Приёмка:** одна схема и запись, два actor с разными field permissions; запрещённое поле не возвращается. Сохранить проверки удаления, текущей версии схемы и record-level denial. Нужен короткий package test, внешние search backends для этого сценария не требуются.

## KRN-03. `not-executed` в write-интеграции tools не означает rollback

**Места:** [GuardedToolInvoker.php](../../packages/tools/src/Internal/GuardedToolInvoker.php):198–222, 281–316; [ToolGuardFixture.php](../../packages/tools/tests/Support/ToolGuardFixture.php):137–180, 189–219; [ToolInvocationTest.php](../../packages/tools/tests/Feature/ToolInvocationTest.php):334–342; [ToolExecutionGuard.php](../../packages/tools/src/Contracts/ToolExecutionGuard.php).

**Контракт и граница вывода:** D90 §3 закрепляет исходы `not-executed/committed/unknown`; P06.2 переводил taxonomy и прямо исключал изменение guard policy. Здесь обнаружен сохранившийся дефект интеграции и её поведенческого доказательства, а не утверждение, что P06.2 должен был расширить свой scope. Default ReadOnlyToolExecutionGuard не допускает write tools. Write guard принадлежит хосту; воспроизведение использует поставляемую в репозитории транзакционную consumer fixture.

**Сценарий:** разрешённый domain-key write handler записывает локальный эффект, затем возвращает output, не соответствующий JSON schema. Invoker преобразует output failure в обычный `ToolResult(NotExecuted)`. Fixture guard возвращает его из `DB::transaction`, не записывает idempotency receipt и коммитит транзакцию. Retry с тем же operation key повторяет эффект. Схожая опасность существует для исключения после записи: invoker перехватывает Throwable и возвращает denial вместо сигнала отката.

**Доказательство:** [repro-tool-output.log](repro-tool-output.log): оба вызова вернули `not-executed/tools.output_contract_failed`; сохранены `2` effect rows, idempotency rows `0`. Подменён только существующий handler на действительно пишущий и возвращающий неправильный output. Тест `invoke_08`, названный проверкой rollback, вызывает read-only `notes.badoutput`, не пишет эффект и проверяет счётчик другого handler; rollback им не доказан.

**Последствие:** consumer, воспроизводящий проверенную в репозитории write-интеграцию, получает безопасный для retry исход при уже сохранённом эффекте. Это нарушение идемпотентности и значения ToolOutcome. Нельзя переносить результат на все возможные host guards: корректный host guard может сам принудительно откатить failed callback.

**Исправление:** согласовать и явно закрепить протокол invoker/guard для ошибок после входа в effect. Для локального domain-key пути output/error denial должен приводить к откату до возврата `not-executed`; нельзя глотать необходимый rollback signal. Для внешнего уже совершённого эффекта сохранить `committed` с output error либо `unknown`, с reconciliation, без обещания отката сети. Исправить официальную fixture и доказать корректность реальной записи; определить, достаточно ли исправления host protocol/fixture или требуется изменение Internal invoker. Сам по себе enum не обеспечивает атомарность.

**Приёмка:** handler пишет строку и даёт invalid output/throw: после результата `not-executed` строк нет; retry не дублирует эффект; успешный committed replay использует receipt. Отдельный provider-key сценарий после внешнего эффекта не превращается в `not-executed`. Тесты в tools package должны проверять DB effect, а не неиспользованный счётчик.

## KRN-04. Resume recurrence теряет ещё не обработанные события

**Места:** [RRuleRecurrenceExpander.php](../../packages/recurrence/src/Internal/RRuleRecurrenceExpander.php):65–103, 159–179, 459–472; [README](../../packages/recurrence/README.md):18; [RRuleRecurrenceExpanderTest.php](../../packages/recurrence/tests/Unit/RRuleRecurrenceExpanderTest.php):REC-09/10.

**Контракт:** бюджет traversal возвращает `partial` с resumable cursor. Возобновление должно сохранять множество occurrences и соблюдать лимиты отдельного вызова.

**Сценарий:** `FREQ=DAILY;COUNT=6`, окно на семь дней, `emitted=2`, `candidateSteps=2`. Первый вызов отдаёт два события. После resume `$scanned` сначала догоняет сохранённый offset, затем увеличивается до проверки лимита. Проверяется глобальный offset, а не работа текущего вызова. Новый cursor уже включает кандидата, который не был обработан; следующая страница пропускает его.

**Доказательство:** [repro-recurrence-resume.log](repro-recurrence-resume.log): страницы `partial(2), partial(0), partial(0), partial(0), partial(0), complete(0)`; суммарно `2` вместо `6`. Контроль с достаточным бюджетом возвращает все `6`.

В том же механизме `$emittedSoFar + $emitted >= limits.emitted` использует лимит как накопительный вместо page budget. Replay prefix и `hasMoreCandidates` обходят источник без тех же проверок времени/steps; это дополнительно делает resume дороже с ростом offset. Точный worst-case runtime отдельно не измерялся.

**Последствие:** события молча исчезают, а consumer получает конечный `complete`, поэтому не имеет признака недостающей обработки.

**Исправление:** разделить абсолютную resume position и счётчики работы страницы; сохранять cursor только после полностью обработанного кандидата; корректно ограничивать emitted текущего вызова. Учесть затраты seek/replay prefix и lookahead в bounded traversal, не создавать безлимитный обход ради проверки наличия следующего кандидата.

**Приёмка:** собрать все страницы серии через cursor: результат равен однопроходному expansion, без потерь и дублей. Проверить candidate budget отдельно от emitted, границу остановки перед кандидатом, EXDATE/cancelled overrides, window end, cursor identity mismatch. Проверки должны быть маленькими и детерминированными.

## KRN-05. Task materializer игнорирует сохранённое продолжение

**Места:** [SqlTaskRecurrenceMaterializer.php](../../packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php):97, 134–139, 184–193, 215–231; [MaterializationTest.php](../../packages/tasks-workflow/tests/Feature/MaterializationTest.php):MAT-03.

**Контракт:** публичная materialization API возвращает bounded receipt с cursor/committed prefix, сохраняет series state и дедуплицирует эффекты. Повторная обработка окна должна продвигаться по оставшимся occurrences. Проблема независима от KRN-04: здесь candidateSteps `100000`, а причиной служит `after: null`.

**Сценарий:** серия из шести ежедневных задач, один window, `pageLimit=2`; три вызова с разными valid operation keys. State и cursor записываются, но expand всегда начинается сначала. Уже созданные задачи превращаются в noop, занимают всю страницу, и последние четыре не достигаются.

**Доказательство:** [repro-task-resume.log](repro-task-resume.log): `created=2`, затем дважды `created=0/noop=2`; cursor остаётся `scanned=2/emitted=2`; задач всего `2`. Контроль [repro-task-full-control.log](repro-task-full-control.log) на том же сценарии с одной достаточной страницей создаёт `6`. Использованы реальный command, SQL store и CreateTaskCommand; fixture разрешает доступ, исключая unrelated policy denial.

**Последствие:** частичная materialization никогда не завершается; retries/wakeup повторяют уже обработанный префикс. Увеличение pageLimit лишь скрывает проблему до следующей длинной серии.

**Исправление:** восстановить валидное продолжение из state и передать его expander, связывая cursor с series revision, window hash, tzdb/library и restore epoch. При смене окна/revision нужен согласованный reset. Продвигать checkpoint только за реально committed occurrences. Нельзя просто записывать/передавать `page.nextCursor`, если task loop остановился раньше конца expanded page из-за времени, conflict или unknown: это пропустит необработанный хвост.

**Приёмка:** несколько малых страниц создают все шесть задач ровно один раз; identical operation-key replay не продвигает работу повторно; fresh keys продолжают. Проверить остановку внутри expanded page, conflict/unknown, смену окна/revision/restore epoch. Исправлять после KRN-04, сохранив отдельный regression test.

## KRN-06. Digest source обходит отказ wakeup admission

**Места:** [SweepRunner.php](../../packages/wakeup/src/Internal/SweepRunner.php):45–58, 155–160; [NotificationDigestDueSource.php](../../packages/notifications/src/Digest/Internal/NotificationDigestDueSource.php):43–52; [NotificationsDigestRegistrar.php](../../packages/notifications/src/Laravel/NotificationsDigestRegistrar.php):48–56; [WakeupBridgeTest.php](../../packages/notifications/tests/Digest/WakeupBridgeTest.php).

**Контракт:** wakeup delivery проверяет сохранённый extension stamp/version; точное совпадение допускается, несовпадение терминально отклоняется. Это подтверждено существующими тестами. [P07.5](../../plans/archive/2026.09.27-№1-COREX-KERNEL/phases/P07/P07.5.json) прямо исключает изменение поведения lease/retries/sweeper; D96 переносит регистрацию на теги, сохраняя admission.

**Сценарий:** настоящий frozen digest имеет due wakeup с устаревшим handler version. SweepRunner отклоняет его, записав `handler-version-mismatch`. В этом же sweep вызывается newly tagged digest due source. Он напрямую вызывает DigestWakeupHandler с заново созданным DigestTick, не проходя проверку сохранённого request/stamp.

**Доказательство:** [repro-digest-stamp.log](repro-digest-stamp.log): wakeup `failed/handler-version-mismatch`, но digest `admitted`, создана `1` delivery; один sweep одновременно считает failure и completion. Использованы реальные providers, schema, DigestBuilder, sweeper, due source и handler; preference fixture выбирает hourly digest. Существующие rejection tests используют recording adapter либо payload с отсутствующим run, поэтому не проверяют этот обход через реальные digest rows.

**Последствие:** работа, отклонённая из-за несовместимого durable handler contract, всё равно выполняется. Прямой путь также не опирается на cancellation/retry/lease admission original request; эти дополнительные ветки установлены чтением, отдельно не воспроизводились.

**Исправление:** дать recovery source тот же admission boundary, что у durable wakeup. Не лечить отказ версии прямым вызовом handler или автоматической заменой версии в старом payload. Если необходима миграция старых запросов — отдельный явный re-admission protocol с новой identity и совместимостью. Сохранить отсутствие зависимости notifications → wakeup: использовать Scheduling SPI в ядре.

**Приёмка:** реальный frozen digest с mismatched/missing stamp и cancelled request не создаёт delivery; valid stamped request допускается ровно один раз; retry/lease fencing соблюдается. Проверять результат в `ntf_deliveries`, а не только число вызовов recording adapter.

## KRN-07. Terminal digest rows вызывают starvation источника

**Места:** [NotificationDigestDueSource.php](../../packages/notifications/src/Digest/Internal/NotificationDigestDueSource.php):26–40, 51–55; [DigestWakeupHandler.php](../../packages/notifications/src/Digest/Internal/DigestWakeupHandler.php):40–41, 63–66, 102.

**Контракт:** DueWakeupSource должен выбирать подлежащую исполнению работу в bounded sweep. Handler обрабатывает только `frozen`, после чего оставляет run в terminal `admitted/skipped`.

**Сценарий:** история содержит старый skipped/admitted run и более новый due frozen run. Source выбирает все состояния, сортирует по period_end и ограничивает LIMIT. Старый terminal префикс занимает весь бюджет снова и снова. `nextDue()` также берёт минимум среди terminal rows.

**Доказательство:** [repro-digest-starvation.log](repro-digest-starvation.log): три sweep с limit=1 выбирают `01:00` старого skipped run; новый run `02:00` остаётся frozen; nextDue всегда возвращает старое время. Выполняются настоящий SQL query/source/handler; builder fixture записывает выбранные окна и возвращает существующий старый run. Отсутствие state predicate непосредственно доказано исходником.

**Последствие:** fallback/recovery через этот source не достигает новых runs при накоплении истории. Когда новый run обслуживается отдельным valid durable wakeup, обычная доставка может пройти; дефект особенно существенен для работы, которую должен восстановить именно source. Он также даёт постоянный past nextDue и завышенные completion counts на terminal no-op.

**Исправление:** применять одну семантику executable state в sweep и nextDue, учитывать KRN-06. Не считать terminal no-op успешной обработкой due work. Убедиться, что source выбирает конкретный run, а не перепланирует новую версию digest при обращении к builder.

**Приёмка:** terminal history длиннее limit + новый frozen run: source обрабатывает новый run в bounded число sweep; после завершения nextDue относится только к оставшейся работе либо null. Проверить skipped/admitted history, равные timestamps и limit boundary. Исправление одной фильтрации не закрывает KRN-06.

## KRN-08. Опции tasks-workflow wakeup не регистрируют handler

**Места:** [TasksWorkflowServiceProvider.php](../../packages/tasks-workflow/src/TasksWorkflowServiceProvider.php):36–69; [TasksWorkflowOptions.php](../../packages/tasks-workflow/src/Internal/TasksWorkflowOptions.php):14–17, 31–41; [SqlTaskRecurrenceMaterializer.php](../../packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php):252, 424–439; [MaterializationPgTest.php](../../packages/tasks-workflow/tests/Pg/MaterializationPgTest.php):255–267.

**Требование:** [D96 §C.9](../../plans/archive/2026.09.27-№1-COREX-KERNEL/decisions/D96.json): package handlers регистрируются тегом `WakeupHandlerDefinition::TAG`. Tasks-workflow поставляет собственный MaterializationWakeupHandler и опции `handler`, `handler_kind`, `handler_version`, `accept_legacy_unstamped`, но не создаёт/тегирует definition. README обещает optional scheduling и versioned registrations.

**Сценарий:** обычные package providers + WakeupServiceProvider, `corex-tasks-workflow.wakeup.enabled=true`, остальные собственные опции оставлены default. Первый materialize коммитит задачи/operation receipt, затем scheduler не находит handler и бросает исключение. Никакого wakeup нет. Package test маскирует отсутствие композиции helper-ом, вручную записывающим `corex-wakeup.handlers` и сбрасывающим registry.

**Доказательство:** [repro-task-wakeup-no-handler.log](repro-task-wakeup-no-handler.log): `Wakeup handler [task-recurrence.materialize] is not registered`, уже создано `3` tasks, operation receipt committed, wakeup requests пусты. Test module-registry mock предоставляет только fingerprint, policy mocks разрешают работу; регистрация production providers настоящая.

**Последствие:** включённая штатная опция не работает при обычной композиции; caller получает failure после commit. Replay сохранённого operation receipt возвращается до schedule и не восстанавливает отсутствующий wakeup. Некоторые из собственных handler options фактически не участвуют в регистрации.

**Исправление:** package-owned scoped handler + immutable definition из TasksWorkflowOptions, регистрация через kernel tag. Не записывать config другого пакета. При включённой интеграции проверить её доступность до эффекта, а scheduling после commit сделать восстанавливаемым/идемпотентным. Учесть provider order и duplicate kind rejection.

**Приёмка:** загрузка только публичных providers с включённой опцией без ручного registry helper позволяет materialize → schedule → sweep. Custom handler kind/version/legacy options отражаются в definition/stamp. Проверить отсутствие scheduler и failure scheduling после domain commit с безопасным recovery; duplicate kind отклоняется при boot.

## KRN-09. Постоянный wakeup key не допускает следующее окно серии

**Места:** [SqlTaskRecurrenceMaterializer.php](../../packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php):424–440; [SqlWakeupScheduler.php](../../packages/wakeup/src/Internal/SqlWakeupScheduler.php):54, 68–81; [MaterializationWakeupHandler.php](../../packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php).

**Контракт:** WakeupScheduler использует immutable request identity: тот же key и другие fire_at/payload дают `ScheduleResult(conflict)`. Task materialization принимает разные windows/revisions и обещает optional scheduling; caller обязан учитывать такой результат.

**Сценарий:** для одной серии последовательно materialize окна `09-01…09-04` и `09-04…09-08`. Handler явно корректно зарегистрирован хостом, чтобы изолировать этот дефект от KRN-08. Оба окна используют `requestKey='materialize:<series-id>'`, но fire_at/payload разные. Schedule первого возвращает created, второго conflict; materializer игнорирует ScheduleResult.

**Доказательство:** [repro-task-wakeup.log](repro-task-wakeup.log): все `6` domain tasks успешно создаются; schedule statuses `created, conflict`; существует только прежний request с fire_at `2026-09-03`, запрос для `2026-09-07` отсутствует. Использован настоящий SqlWakeupScheduler; wrapper только записывает возвращаемые статусы.

**Последствие:** следующее окно не получает wakeup, хотя materialize возвращает успех. После completion/cancellation старого запроса новый key всё равно не создаётся, поскольку immutable row остаётся в журнале. Прямая wakeup delivery также повторно использует payload исходного window; она не формирует новое окно автоматически.

**Исправление:** определить durable identity по согласованной единице работы, включая series revision и materialization window/generation; одинаковый запрос replay должен сохранять key, новая работа — получать новый. Явно обработать `conflict`, не скрывая потерю scheduling. Связать committed materialization и schedule recovery, чтобы crash/failure после commit не оставлял работу навсегда без wakeup. Не менять scheduler на перезапись существующего request ради обхода его идемпотентности.

**Приёмка:** одно окно повторно → existing; следующее окно/revision → корректный самостоятельный request; old completed/cancelled не блокирует новую работу. Конфликт identity виден и восстанавливается по принятой семантике. Проверить recovery после commit и повторный wakeup delivery, отдельно от pagination KRN-05.

## Покрытие аудита и доказательства

Канонический вход: [plan.json](../../plans/archive/2026.09.27-№1-COREX-KERNEL/plan.json), carriers фаз/items и решения. Устаревшая md-ссылка из ACTIVE не использовалась как источник состояния. План имеет status `done`; проверялся итоговый runtime всех фаз, включая унаследованные дефекты. Привязка к P09 strict typing не означает, что конкретный bug возник именно в P09.

| Фаза | Что сверено | Результат в исследованном scope |
| --- | --- | --- |
| P00 | Принятые package/kernel maps, glossary, архитектурные запреты, итоговая доктрина | Соотнесены с дальнейшими фазами и gates; предметные разрывы приведены выше |
| P01 | PHP84 profile, manifests, ratchets и границы источников доказательств | manifest/ownership gates проходят; `scripts/php84 --check` — PHP 8.4.26, extensions complete |
| P02 | Разделение operations/tools/webhooks, независимость kernel, vendor/namespace rename | graph и rename gates проходят |
| P03 | Feature-module layout, Internal boundary, runtime/testing separation | layout проходит: 1418 файлов, 0 violations |
| P04 | API/SPI scan, DTO/errors, ProblemRegistry, минимальные публичные швы | strict surface/error gates проходят; field authorization и effect outcome имеют runtime gaps KRN-02/03 |
| P05 | Options parsing, boot/register composition, resident lifetimes и runtime globals | config/globals/reference gates проходят; исследованы scoped dependencies и late resolution |
| P06 | InputEnvelope, RFC 9457, tool taxonomy, CloudEventCodec, webhook retry/egress/store | structural errors/API проходят; KRN-03 требует восстановления поведенческого доказательства write boundary |
| P07 | Tenancy/context/provisioning, impersonation claim/audit, native access/bridge, Scheduling tags | dependencies проходят; KRN-06/07/08 на реальных композиционных путях |
| P08 | SchemaSet registry/migrator, lifecycle/wave/template paths, baseline ownership | schema/table gates проходят, 21 SchemaSet; KRN-01 показывает runtime mismatch |
| P09 | Strict-quality configuration, source changes, behavior receipts Search/Transfer/lifecycle, финальные решения | quality ceilings проходят; KRN-04/05/09 выявлены в итоговом runtime |
| P10 | Hermetic lane declarations, package dependency declarations, tool worker ready/deadline/error handling | manifest/API/config checks проходят; историческое hosted CI не выдаётся за новый запуск |

**Новые проверки:** 16 коротких статических гейтов, все exit 0: manifests, layout, schema, graph, ownership, table ownership, table write ownership, config composition, runtime globals, rename, naming, error registry, strict PHP API, quality ceilings, configuration reference, package docs. Команды, длительность и ссылки на сырой вывод сохранены в [checks.json](checks.json). Суммарно около 42 секунд; самый долгий отдельный gate — около 7 секунд.

**Диагностика:** 11 отдельных процессов, в том числе 9 failure scenarios и 2 положительных контроля; каждый менее секунды, суммарно около 2.3 секунды. Recurrence имеет дополнительный положительный контроль внутри своего сценария. SQLite сценарии используют только `:memory:`. Module schema воспроизведена в отдельно созданной PostgreSQL базе `corex_kernel_audit_test`; другие базы не изменялись. Сведения о сохранности рабочей копии и удалении этой временной базы: [workspace-verification.json](workspace-verification.json).

**Ограничения:** по просьбе владельца не запускались полные package/root SQLite/PG suites, coverage, полноценные Pint/PHPStan/Rector прогоны и длительные нагрузочные проверки. Новое GREEN для них не заявляется. Динамические проверки служат точечными доказательствами, а не заменяют всю CI матрицу. Статический audit охватывает решения/приёмку всех фаз и критические runtime paths, но не утверждает построчное доказательство корректности каждого файла.

[D125](../../plans/archive/2026.09.27-№1-COREX-KERNEL/decisions/D125.json) разрешает пропуск release/RC qualification; это не finding. [D133](../../plans/archive/2026.09.27-№1-COREX-KERNEL/decisions/D133.json) отменяет текущие mutation gates; они не запускались и не считаются нарушением. Требование полного SemVer freeze/release не подменяет принятый pre-release scope. Внешние premise не использовались: выводы опираются на код, локальные контракты и короткие воспроизведения.

## Передача исполнителю

1. Исправить KRN-02 и KRN-03 отдельными изменениями по границам Search и Tools; начать с regression, реально проверяющего field disclosure/DB effect.
2. Исправить KRN-01, сохранив единый источник module ownership и согласовав документированный template путь с lifecycle.
3. Исправить KRN-04, затем KRN-05. Сохранять отдельные tests: правильный expander не исправляет игнорируемый cursor materializer.
4. Исправить KRN-06 и KRN-07 совместно в notifications/scheduling boundary, с независимыми assertions admission и fairness.
5. Исправить KRN-08 и KRN-09 совместно как package composition + durable scheduling, сохраняя две отдельные проверки. Учесть scheduling recovery после domain commit.

Исполнитель должен сохранить существующую рабочую копию: [baseline.json](baseline.json) содержит HEAD и hashes 326 ранее изменённых/неотслеживаемых файлов. Гейт графа обновляет свой report; исходное состояние этого файла восстановлено по HEAD и проверено по начальному git status, новый результат находится в [package-graph-report.json](package-graph-report.json). Не редактировать generated views напрямую, не менять статусы канонического плана ради формального GREEN, не ослаблять gates и не удалять существующие проверки. Package fixes/tests размещать в пакетах-владельцах. Изменение публичного SPI/DTO, если понадобится, сверить с Api/Spi manifest, CHANGELOG и BC policy; предпочесть Internal repair без лишнего расширения поверхности.

Запускать сначала только адресные короткие regressions. Сохраняется указание владельца не выполнять долгие прогоны; mutation work требует отдельного запроса по D133. После исправления пересмотреть конкретные findings и затронутые соседние ветки. Новый полный аудит не нужен без нового scope.

Для повторения SQLite case: `php8.4 audits/2026-10-08-corex-kernel/reproduce.php <case>`; имена case перечислены в reproductions.json. PostgreSQL case требует **отдельно созданной** базы ровно `corex_kernel_audit_test` и `PGSQL_DATABASE=corex_kernel_audit_test`; PHP diagnostic отклоняет другое имя перед reset schema. [run-reproductions.py](run-reproductions.py) запускает все cases с таймаутом 15 секунд каждый; база автоматически им не создаётся. Diagnostic содержит наблюдения исходной ошибки, не готовый passing regression: при исправлении преобразовать их в assertions ожидаемого контракта в owning package.
