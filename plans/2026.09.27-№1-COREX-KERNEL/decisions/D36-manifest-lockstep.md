---
id: D36
date: 2026-09-28
status: accepted
item: P01.1
items: [P01.1, P02, P07, P09]
supersedes: []
superseded_by: null
---
# D36 — manifest-lockstep (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, `task:plan-design … P01`, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6).
**Evidence:** RAG:— `artifacts/P01-design/facts/README.md` F2 (`composer validate --strict` → 16× «unbound version constraints (@dev)»), F3 (прототип Composer: `branch-alias` + `^1.0@dev` + `minimum-stability: stable` валиден в root и standalone; транзитивный пакет без флага в корне → «does not match your minimum-stability»).

## Solution

1. Каждый пакет `packages/*/composer.json`: `extra.branch-alias = {"dev-main": "1.0.x-dev"}`; межпакетные ссылки — `^1.0@dev` (вместо `@dev`).
2. `minimum-stability: stable` + `prefer-stable: true` в root **и** в каждом пакете; каждый installable корень (root и каждый пакет для standalone-установки) перечисляет всё транзитивное замыкание монорепо-пакетов с `^1.0@dev` и `repositories` path на каждый из них.
3. Временные исключения D16, владелец снятия — P07: root и `packages/tenancy` явно требуют `axiomasoft/stancl-tenancy: 4.0.0-alpha1`, `axiomasoft/stancl-jobpipeline: 2.0.0-alpha1` (точные версии в корне разрешают alpha); `packages/access` — `axioma-studio/azguard-core ^0.2@dev` + VCS. Других VCS/dev/alpha нет.
4. `composer validate --strict --no-check-lock` для каждого `composer.json` — exit 0. Root lock — dev-окружение монорепо, CI делает `composer update`; свежесть lock не гейт (`--no-check-lock`), `composer audit --locked` проверяет lock, разрешённый в той же job (D39).
5. P02 при переименовании в `axiomasoft/corex-*` сохраняет форму ограничений; P09 при split заменяет `^1.0@dev` на релизный lockstep-диапазон.

## Why

- `@dev` без границы — предупреждение, ломающее `validate --strict` (D18); `branch-alias` даёт границу мажора без тегов.
- Флаги стабильности действуют только в корне (F3) — без явного замыкания stable-корень не разрешается.
- Отклонено: оставить `minimum-stability: dev` в пакетах (маскирует случайные dev-зависимости; противоречит D16); `self.version` (pplx-11: только для umbrella, при path-репозиториях неприменим).

## Consequences

P01.1 правит 21 манифест; P02 переносит форму на новые имена; P07 снимает исключения п.3; P09 — релизные диапазоны.
