---
id: D92
date: 2026-09-20
status: accepted
item: P16
items: [P16, P16.1, P16.2, P16.3, P16.4, P16.5, P16.6, P16.7, P16.8, P16.12, P16.13]
supersedes: []
superseded_by: null
---
# D92 — Физический профиль без второго FQCN rename

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:✅ Composer schema допускает один PSR-4 prefix с массивом каталогов;
RAG:— D1/D4/D85–D88, `research/P16-design.md` §2.

## Решение

P16 меняет физическую группировку каталогов, но сохраняет каждый существующий после P15
PHP FQCN байт-в-байт. Для layer-first публичных namespaces пакет объявляет более длинные
PSR-4 prefixes, указывающие на один или несколько subsystem-каталогов; общий package prefix
остаётся fallback для module/provider и уже согласованных путей. Classmap и ручные `require`
не вводятся. Внутренние FQCN также не меняются: цель фазы — доказуемая структура без второй
волны символических переименований.

## Почему

Перенос публичного символа в новый namespace был бы новым breaking change после закрытого
реестра P15 и потребовал бы отдельного G-API. Composer поддерживает multi-directory PSR-4
mapping, поэтому физическую subsystem-first структуру можно получить без смены API.

## Consequences

Каждый move-item проверяет `dump-autoload --strict-psr --strict-ambiguous`, class discovery,
точный FQCN manifest до/после и package tests. `extra.corex.public-api` не меняется из-за
структуры; P16.12 обновляет его только при доказанном изменении декларативной поверхности из
producer-фаз.
