# Задание новой сессии — аудит и улучшение всего PLAN1

**Owner-selected model:** gpt-6-astra.
**Reasoning effort:** high.
**Scope:** весь план 2026.09.15-№1-COREX-FLEXCRM-REMAINING, P1–P11; исследование практик, аудит согласованности и исправление executable design до реализации.
**Author of this launch brief:** gpt-5.6-sol; Astra указана как запрошенный исполнитель, не автор уже сделанного аудита.

## Запрос владельца

Провести полный аудит плана моделью Astra/high, согласовать все фазы и проверить лучшие практики. Отдельно проанализировать пакет AzGuard (владелец явно уточнил имя после первоначального «Asgard»), включая удобный для кастомизации паттерн панелей и остальные подходы. Дополнительно через Perplexity найти аналогичные open source пакеты, разобрать их практики по исходникам и официальной документации и скорректировать план. Цель — конкретный, непротиворечивый дизайн с описанными решениями, границами, зависимостями, проверками и условиями исполнения, чтобы во время разработки не приходилось изобретать архитектуру. Допускаются другие обоснованные улучшения дизайна.

Это авторизует исследование, полный design review и правку данного плана. Это не исполнение P1.1 или других продуктовых items и не разрешение менять исходники пакетов/приложений, Contracts, DDL, зависимости, релизы, внешние системы или чужие планы. Пользователь отдельно закрепил Astra/high для этой сессии; не подменять её Sol/xhigh из фиксированного маршрута task:plan-audit. Прямое задание не требует искусственного запуска несовместимой Task-команды. Shared Task runtime, provider mappings и плагины не менять.

## 1. Полный аудит согласованности

Начни с AGENTS.md, docs/dev-doctrine.md, plan.md, owner briefs, действующих решений, research/05-integrated-design.md, roadmap/handoff и текущих состояний. Затем прочитай все 11 phase contexts и все 78 item specs, соответствующие stable design documents, RAG records и плановые checkers. Читай порциями без обрезанных обязательных inputs; веди перечень просмотренных файлов и их hashes. Текущее starting HEAD сверяй фактически; bad0b21 — опубликованная базовая точка исходного полного дизайна, не обещание неизменности рабочего дерева.

Проверь полноту 52 R и 365 исходных parent criteria и всех выделяемых atoms; accepted/proposal/no-delta и source/test/installed/released/hosted/effective/consumer классы не смешивать. Для каждого item должны совпадать цель, исходный R/AC, producer→consumer, версия/схема артефакта, Required Reads, конечный Files allowlist, gates, Validation, Deliverables и GREEN. Найди отсутствующие producer paths, придуманные версии, циклы, самозависимости, неисполняемые проверки, дублирование механизмов, противоречия между фазами и чрезмерные/недостаточные условия. Особое внимание — P4/P5 digest/wakeup DAG, P2 adoption перед P3–P8, P9 version-vector/source/split/dist/lock, P10 полнота DI/AP/FE/month/provider и P11 независимый accepting owner/точные consumer pins/finite foreign write/partial recovery/self-invalidating anchors.

Проверь L0-агностичность, независимость standalone engines и AzGuard, отсутствие зависимостей вверх и продуктовой политики в CoreX; default-off/boxed/optional profiles, совместимость PHP/Laravel, реальную тест-топологию и non-CRM consumer. Не считать review depth согласием на phase audit и не объявлять future receipts существующими.

## 2. Отдельный обязательный анализ AzGuard

Владелец явно подтвердил: AZguard. Канонический локальный источник — /home/vostrikov/projects/packages/azguard. Не подменять его одноимённым Asgard/AsgardCMS или другим проектом. Анализ только read-only: прочитай его AGENTS.md, документацию, Contracts/providers/registry/panel/permissions/roles и тесты; закрепи package name, source commit/working delta, license/version и exact file/symbol references. Разбери модель панелей: scoped namespaces, декларации и регистрация, настройки/customization/overrides, композиция, typed enums/DTOs, DI/driver boundaries, discovery/scaffolding, defaults/deny precedence, cache/invalidation и тесты; покажи, как ей пользуется потребитель. Отдели authorization panels AzGuard от headless entity/capability panels CoreX, модульной композиции и UI panels — одинаковое слово не доказывает общий контракт.

Составь таблицу «паттерн → исходник/тест → польза → ограничение/failure mode → применимость к CoreX/Flex/consumer → affected item». Зафиксируй, что переиспользовать как seam, что оставить владельцу исходного пакета и что отвергнуть. Не копировать движок ролей или продуктовые vocabulary в CoreX. Разбор всех остальных полезных подходов пакета обязателен, не ограничиваться панелями.

## 3. Обязательное дополнительное исследование через Perplexity

Используй Perplexity для новых нейтральных запросов о публичных практиках и аналогичных open source Laravel/PHP пакетах. Соблюдай D4/D5/D6: не передавать CoreX/FlexCRM, внутренние R/AC/DI, пути, private схемы/профили/исходники как предмет веб-запроса. Для AzGuard опираться на разрешённый локальный source; web queries посвящать общим механизмам. Найди действительно сопоставимые проекты по modular loading/extension registries, headless panel descriptors/customization, typed configuration/permissions, storage/tenancy boundaries и другим существенным seams плана; выбери достаточный набор по релевантности, а не популярности или фиксированному числу.

Для каждого выбранного проекта проверь канонический репозиторий, актуальный immutable ref/version, license/maintenance/compatibility, реализацию и тесты подхода. Perplexity summary — retrieval, не proof. Каждое ключевое изменение сверяй минимум с двумя независимыми primary origins; точные local выводы отдельно маркировать RAG:—, общую публичную практику RAG:✅. Сохрани точные queries, реальные tool responses и digests, прямые ссылки, проверенные claims, alternatives/selection, failure modes и cross-phase impact. Отмечай tradeoffs, устаревшие/неподходящие подходы и причины отказа; не требовать установки исследуемых пакетов или импортировать их зависимости без отдельного решения.

Perplexity может долго думать, а окно закрываться автоматически. Дождись завершения каждого текущего запроса; закрытие окна само по себе не failure. При фактическом login/tool/no-answer/unconfirmed результате немедленно сообщи владельцу и сохрани pending; отдельный supplementary query должен закрыть gap. Не выдавать fallback или одинаковые пересказы за successful mandatory step.

## 4. Исправление плана после независимой фиксации findings

Сначала запиши evidence-bound finding для каждого продемонстрированного дефекта/полезного улучшения, затем внеси обоснованные изменения в owning stable phase/item/Routing/Required Reads/Files/Validation/Deliverables и integrated design. У каждой правки — проблема, evidence, решение/альтернативы, affected R/items/producer consumers и observable acceptance. Сохрани исходные 52 R/365 parents, accepted A01–A09, исходные критерии/thresholds, source snapshots и append-only историю. Accepted decision не переписывать по смыслу: добавлять новый D# с supersession/actor. Улучшения вне frozen scope представить отдельными предложениями; не добавлять новые продуктовые результаты или future requirements ради GREEN.

Сними все разрешимые архитектурные вопросы сейчас: конкретные типы/interfaces/DTO/errors/defaults, version/storage/transaction/clock/ordering/idempotency/retry/timeout/cancellation/restore/upgrade semantics, примеры использования, отрицательные случаи, source/licensing/ownership, per-package проверки и точные producer artifacts. Не оставлять разработчику «выбрать паттерн/библиотеку/контракт по ходу». Не маскировать неизбежные external receipts/finite owner grants/calendar windows готовыми данными: для них именованный owner, точный packet/receipt и resume predicate. Если есть реальный новый owner choice, подготовь reviewable варианты до вопроса, продолжая независимую работу.

## 5. Результат и проверки

Сохрани отдельные файлы: findings/whole-plan-astra-review.md (initial findings и их resolution/evidence), research/06-azguard-patterns.md, research/07-open-source-patterns.md, artifacts/astra-whole-plan-review/ (actual responses/hashes/input inventory/checks). Они принадлежат этому плану; не менять foreign/Brain archives. Обнови affected plan contracts и owned generated views через их генераторы; восстанови byte-fresh runnable bundle и named plan-lint/downstream после финальных stable изменений. Значимые негативные проверки должны демонстрировать enforcement, а не копировать implementation. Новые обязательные design inputs включи в finish seal/checker по существующему owning правилу, чтобы indirect research changes не оставляли ложный current finish.

Выполни содержательный integrated pre-mortem/compatibility review и подготовь artifacts/design-finish.md только когда весь дизайн действительно согласован, mandatory RAG successful, all stable input hashes current и structural gates green. Никакого phase/runtime/consumer acceptance GREEN по документации. Если новый unresolved owner choice или RAG gap препятствует finish, сохрани exact pending/resume, без fake verification. Исполнение item не начинать даже после design finish в этой authoring сессии.

В конце покажи ключевые findings, что исправлено, почему выбранные практики подходят, что сознательно отвергнуто, результаты проверок и оставшиеся только реальные gates. Дай одну точную рекомендацию следующего шага с маршрутом из актуального плана. Не обещай отсутствие любых будущих вопросов: докажи отсутствие незаданных существенных design choices, отличая их от внешней readiness.
