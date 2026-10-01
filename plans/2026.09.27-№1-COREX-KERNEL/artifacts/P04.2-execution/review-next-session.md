Репозиторий: /home/vostrikov/projects/packages/corex.
Выполни независимое ревью и необходимую валидацию реализации PLAN3 P04.2 (план 2026.09.27-№1-COREX-KERNEL). Исполнение оставило код в рабочем дереве без commit; P04.2 ещё In progress. Не начинай заново и не переходи к P04.3.

Сначала прочитай AGENTS.md, plans/ACTIVE.md, immutable phases/P04/P04.2.md, решения D63/D64/D67 и artifacts/P04.2-execution/implementation-handoff.md. Каталог артефактов находится внутри указанного плана. Финальный кандидат: candidate.patch + transformation-manifest.json. validation-transfer.json отделяет прежние результаты от финального состояния.

Проверь соответствие контрактам, реальные дефекты и качество последних DI/DTO/TaskCompletion изменений. Запускай только необходимые проверки по P04.2; переиспользуй результаты для действительно неизменившихся входов. Не устраивай повторные циклы ревью или лишние перепроверки. Старый validation-result.json относится к предыдущему patch, а не ко всему финальному кандидату.

Особое внимание: восемь новых Laravel-регистраторов и их finite public-api declarations; scoped Golden semantic delta и вручную добавленные signature records; root TaskCompletion filter; актуальные source hashes и sealed replay equality. Не повышай ceilings, не ослабляй assertions/negative controls и не используй bulk GOLDEN_UPDATE.

Известные незавершённые проверки: конечный analyse/lint, TaskCompletion filter, root/package suites и anti-weakening; необходимая проверка актуальности PG/mutation/прочих gate evidence и финального replay. Все 34 мутации и root PG 240 тестов ранее проходили; последний Arch 92/92 прошёл до остановки. Последний analyse прерван владельцем. Команды через scripts/php84; перед PG db-test-preflight; DB suites serial (root corex_test:5432, packages corex_test:5434).

Исправь подтверждённые дефекты в рамках P04.2, проверь только затронутую область, затем сохрани независимый verdict и реальные результаты. При выполненных условиях приёмки закрой P04.2 штатным Task tooling и сделай scoped commit с обычными hooks. Если остаются реальные незавершённые условия, сообщи их честно. Никаких push/release/P04.3.
