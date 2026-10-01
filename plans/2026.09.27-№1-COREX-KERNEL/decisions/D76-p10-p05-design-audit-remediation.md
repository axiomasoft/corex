---
id: D76
date: 2026-10-01
status: accepted
item: P10
items: [P10, P10.1, P10.2, P10.4, P05, P05.1, P05.2, P05.3]
supersedes: []
superseded_by: null
---
# D76 — P10/P05: правка дизайна по design audit (accepted)

**Actor:** repository owner (разрешение на правку в сессии аудита); запись и выбор вариантов — auditor/designer claude-opus-5-5 по D72.
**Evidence:** сообщение владельца после вердикта ATTENTION: «я хочу, чтобы ты свою часть закрыл, чтобы я потом моделью кодекса выполнил вот этот вот план дизайн finish и начал разрабатывать… чтобы ты закоммитил и запушил». RAG:— `findings/P05-P10-design-audit.md` (A1–A4, m1–m6; fingerprint `f188df5f5ff304b1ed28672b74c7fee914843f97a6a6cb8a8c0b9d53e698bd0d`); `.github/workflows/ci.yml:619-649,815-829,965-968`; `composer.json` (`test:packages`, `pestphp/pest ^5.2`); `decisions/D42-laravel12-test-stack.md`; `artifacts/P10-design/facts/repro-root-tests.txt`.

## Решение

1. **A1.** P10.1 не требует `success` у джоба `Remaining cohort package aggregator`: критерий item — в логе джоба блок `==> packages/access` проходит. Джоб исполняет `composer test:packages` по всем пакетам и зеленеет в P10.4, где это проверяется явно.
2. **A2.** Изменение `php-api.json` пакета сопровождается записью в `CHANGELOG.md` этого пакета в том же item — следствие D74 п.9. P10.2 получает в Scope и Files CHANGELOG 20 пакетов и проверку `contract-guard` от базового commit item; в Files добавлены `tests/Arch/CoreZoneTest.php` и `tests/Arch/ErrorContractTest.php`. P05.3 получает CHANGELOG в Files на случай изменения манифеста.
3. **A3.** Скрипт `test:hermetic` определяется в P10.1 вместе с `scripts/ci-local.sh`, который его вызывает; зелёным его делает P10.4 (уточнение распределения D74 п.5 по items, норма п.5 не меняется). `ci-local.sh` в лейне Laravel 12 применяет в копии то же переопределение Pest `^4.7`, что и hosted-лейн (D42).
4. **A4.** Две команды Validation P05.3 заменены: facade-grep использует `:(glob)`-pathspec и исключает весь мост; проверка «нет новых захватов соединения» сравнивает число строк по файлам между базой и кандидатом, а не строки diff.
5. **Hosted CI на каждом item P05.** P05.1–P05.3 заканчиваются push и чтением hosted CI с нулём джобов `failure` — так же, как items P10 (D73 п.4). Отказ матрицы обнаруживается в item, который его внёс, а не в P05.4.
6. **Независимое ревью продуктовых правок P10.4.** Если ремонт лейнов в P10.4 меняет поведение кода в `packages/*/src`, этот diff до закрытия item проходит независимое ревью (`cursor/grok-4.7/high`, свежий контекст) — применение D73 п.7. Остальной P10.4 остаётся `light` (D75).
7. **`skipped` — не отказ.** Правило D73 п.3 читается как «ни одного джоба `failure`»: джоб `Mutation registry` исполняется только на `pull_request` и на push имеет статус `skipped`. Реестр mutation-кейсов проверяется локально командой `mutation-check.sh --all` в items, которые добавляют кейсы.
8. **Уточнение D69 п.1.** Реализация `RuntimeConfigCheck` — тип слоя композиции пакета: ей разрешено читать `<Pkg>Options`. Runtime-сервисы по-прежнему получают значения.
9. **Уточнение D70 п.1.** В список static-registrars, приводимых к `PackageRegistrar`, входит `SavedViewsRegistrar` (panels).
10. V3-ветка P10 (оставить `require --no-update` в лейне Laravel 12) допустима в Validation P10.1 с записью числа шагов и причины.

## Почему

- A1–A4 делали items незакрываемыми либо красными на корректном результате: исполнитель упёрся бы в проверку, которую нельзя выполнить, не нарушив Scope. Каждая воспроизведена командой на commit `39ae489a`.
- п.5: P05 меняет boot и время жизни биндингов во всех пакетах; D73 принят именно затем, чтобы отказы hosted-матрицы не копились до конца фазы.
- п.6: исправление несовместимости с Laravel 12 — продуктовое изменение, которое hosted CI подтверждает по зелёным тестам, но не по корректности поведения.
- Отклонено: перенос `ci-local.sh` в P10.4 (P10.1–P10.3 остались бы без локального воспроизведения CI); требование PR-запуска ради джоба `Mutation registry` (меняет модель доставки плана); обход `contract-guard` исключением для P10.2.

## Consequences

Изменены `phases/P10/{P10,P10.1,P10.2,P10.4}.md`, `phases/P05/{P05.1,P05.2,P05.3}.md`, `research/P10-design.md`, `research/P05-design.md`, `artifacts/P10-design/examples.md`, `artifacts/P05-design/examples.md`, строка 17 execution-sheet. Маршруты, группы и порядок строк 14–20 не менялись. Fingerprint `f188df5f…` и вердикт аудита по нему устарели: нужен новый `task:plan-design … finish` и design audit независимой моделью либо принятие владельцем (прецедент D66). Аудит и правка выполнены одной моделью с автором дизайна — независимой проверкой эта запись не является.
