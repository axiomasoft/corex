# Новый промпт: D133 отменяет мутационную работу

$ task:plan-run 2026.09.27-№1-COREX-KERNEL P09.13

Продолжи в `/home/vostrikov/projects/packages/corex` только по текущему canonical JSON и `plans/ACTIVE.md`. Прочитай `decisions/D133.json`: владелец 2026-10-07 отменил ВСЕ дальнейшие мутационные прогоны до конца PLAN3 до стабилизации ядра. Не запускай Infection, mutation.py (в том числе probes/self-test), mutation-check.sh, MSI или replay ledger. Старый промпт передачи отменён; он сохранён только как история в `next-session-prompt-superseded-by-D133.md`.

P09.12 закрыт done_with_deviations: 95% не достигнуты, текущего GREEN mutation acceptance нет. Полезные ordinary tests и исправления тестовой инфраструктуры сохранены. Подтверждённого production-кодового дефекта, исправленного по результатам mutation testing в этой сессии, нет.

P09.13 теперь владеет только ordinary Transfer resume/idempotency/partial-failure evidence. P09.14 — ordinary Search/Meilisearch behavior. P09.15 lifecycle и P09.8 ordinary code review/CI сохраняются. Три mutation CI jobs намеренно отключены по D133, их skips не являются отсутствующим required-success доказательством. Не возобновляй отменённый scope из старых решений/досье/исторических score/промптов.

Task runtime: `python3 /home/vostrikov/projects/packages/swissknifeman/packages/task/scripts/plan.py`. Installed adapter может ссылаться на старый plan-work.py; актуальный канон — `packages/task/commands/plan-run.md` в swissknifeman. Start с наблюдаемым actor/session; source plan менять только через plan.py. Не читай generated views и огромный context полностью.

Код и обычные тесты предыдущей сессии сохранены в рабочем дереве/owning scoped commit; смотри git log и git status. Подробности: `artifacts/P09.12-execution/work-state.json`, `latest-diagnostics.json`; старые результаты диагностические, не текущий acceptance. Были 40 runner tests, 13 PendingPool tests, дополнительные boundary tests; новые мутационные проверки не нужны и не разрешены.

Сохраняй чужой dirty P09.9 stack proposal, knowledge и projections D03/D120/D131/D132/P09.5/P09.9/plan/handoff/roadmap/status. Не используй git add . или reset/restore всего дерева. Собственные Redis/S3 mutation fixtures остановлены; shared PostgreSQL не тронут. При ordinary PG tests применяй db-test-preflight и изоляцию *_test.

Начинай следующий item только при явном запуске владельцем этой новой сессии. Дай результат, реальные обычные checks и точную следующую команду; без молчаливого production/API/schema change, без публикации и автоматического нового review.
