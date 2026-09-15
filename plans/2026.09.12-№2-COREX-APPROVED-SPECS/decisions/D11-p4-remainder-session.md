---
type: workflow
id: D11
date: 2026-09-14
status: accepted
item: P4
items: [P4, P4.2, P4.3, P4.4, P4.5]
supersedes: []
superseded_by: null
---
# D11 — Одна новая сессия для остатка P4

**Date:** 2026-09-14
**Status:** accepted
**Actor:** Codex, current owner-directed plan metadata amendment; no product execution or new route attestation.
**Evidence:** RAG:— latest owner request: «сгруппировать несколько пунктов … фазу до конца … подкорректируй план … остаток … четвертой … выполнить сразу»; P4.1 is GREEN, P4.2–P4.5 are Not started.

## Решение

Объединить P4.2–P4.5 в рекомендуемый B4 и дать один новый запуск `task:plan-exec` со всеми четырьмя ID. Это полный scope новой сессии, а не атомарный dispatch с неготовыми входами. Последовательность: P4.2 → P4.3 → P4.4; P4.5 использует подготовленные core/modules artifacts, отдельный приватный fixture и собственные PRIVATE-AZGUARD/PRIVATE-READ gates. Максимальный route implementation/high; Review full применяется к ограниченному private-source/credential риску P4.5, остальные проверки остаются своими.

После каждого GREEN item сохранять доказательства и закрывать item, затем продолжать совместимый готовый остаток в этой же сессии. После всех GREEN закрыть P4 inline и выдать отчёт; P5 не исполнять. Не запрашивать повторно разрешение на уже порученную локальную работу. Реальные пропуски source/ref/доступа/поддерживаемых runtime записывать точно, продолжая независимую доступную работу. Обычная ошибка теста или устаревший generated carrier требует диагностики/локального исправления, а не новой сессии.

Источник экспорта — отдельно зафиксированный committed candidate SHA после нужных изменений, не неявный HEAD и не dirty worktree. Проверенные локальные кандидаты P4.1 можно квалифицировать как локальный транспорт по P4.2; это не опубликованные remote channels. Новый corex/api остаётся вне утверждённого four-package public allowlist: не добавлять его скрыто и не подменять full P4 matrix прежним API consumer proof.

## Почему

Владелец прямо попросил пакетное выполнение до конца фазы, чтобы не запускать каждый пункт вручную. Совместный source/provider/export контекст позволяет повторно использовать подготовку. Разделение приватных и публичных fixtures сохраняет границы доступа внутри одной сессии; отсутствие приватного source/ref не делает публичную работу недоступной.

## Consequences

Изменяются только Routing, phase execution handoff и roadmap; Intent/Files/Validation/Deliverables и GREEN история P4.1 сохраняются. Авторизация группировки не предоставляет private credential, external publication, dispatch, tag/push/fork или public-major grant. При отсутствии PRIVATE-AZGUARD/PRIVATE-READ P4.5 не может стать GREEN, и P4 нельзя объявить полностью завершённой. Persisted context: `cold-start-root: owner-requested-new-session`, checkpoint `findings/P4-remainder-session-checkpoint.json`. Это подготовка запуска, не выполненная фаза.
