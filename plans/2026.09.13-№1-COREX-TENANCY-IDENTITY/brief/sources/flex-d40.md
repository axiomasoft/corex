---
id: D40
date: 2026-09-11
status: accepted
item: P11.1
items: [P6.26, P11.1, P11.4, P11.7]
supersedes: []
superseded_by: null
---

## Решение

Прямой регистрации в base CRM и solution applications нет. CRM вообще не создаёт учётные записи или access membership. Central control plane после оплаты запускает provisioner; тот поднимает отдельную account DB организации и автоматически создаёт initial owner access. Домен сервиса в FLEX-2 не угадывается: владельцем реализации остаётся план FLEX/P6.26.

Дополнительные учётки и доступ также заполняет внешний provisioning-контур. CRM предоставляет вход и последующие auth workflows уже созданным пользователям, но не публикует register/invitation/access-creation endpoints. Начальная роль владельца при provisioning назначается сервером.

Boxed/development сохраняет локальный Fortify credential в единственном `users.password_hash`. Пароль или hash между сервисами не копируется: provisioned local user получает одноразовый Fortify password-setup flow. Cloud identity остаётся проекцией central IdP без local password/reset/2FA и без fallback на локальные credentials.

## Почему

Владелец уточнил модель как аналог централизованной покупки и развёртывания: организация и её база появляются только после оплаты и работы установщика. Публичный signup внутри отдельного CRM deployment обходит billing/provisioning, может создать account без владельца инфраструктуры и расходится с DB-per-account.

## Consequences

- `Features::registration()` выключен во всех product applications; `/register` не является backend endpoint.
- P11.4 не может реализовывать старую ветку registration/invitation: перед исполнением item уточняется как integration с provisioning-контуром или снимается как no-op.
- Dev fixtures создаются seed/test harness, но не через публичный signup.
- Контракт initial provisioning фиксирует границу и инварианты, но не дублирует API central control plane из плана FLEX.
- `wsp_invitations` остаётся в утверждённой схеме, но текущий auth-профиль не использует её как CRM onboarding endpoint.
