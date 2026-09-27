# P00 — Внешние факты дизайна (синтез)

Внешние решения P00 — только правила именования/границ пакетов; все покрыты свежими (2026-09-27) captures верхнего слоя
`artifacts/research-rag/`. Новых запросов нет — no-search record: `artifacts/P00-design-rag/README.md` (B7: «не ищется —
покрытое свежими captures»).

| # | Факт | Влияние на P00 | Источник |
|:--|:--|:--|:--|
| E1 | Пакет = независимая capability или шов интеграции, не «пакет на слой»; contracts не зависят от фреймворка | критерий пакета P00.1 (F1, F9); merge `modules` в ядро | RAG:✅ `artifacts/research-rag/pplx-02-php-platform-kernels.md` §1 (Sylius, Shopware) |
| E2 | Public API / Extension SPI / Internal — три разных обещания; документированные namespace | `CoreX\Attributes\{Api,Spi}` (F12), зарезервированный сегмент `Internal` | RAG:✅ pplx-02 §2; pplx-01 (Symfony BC promise) |
| E3 | PHPStan ≥2.1.13 (bleeding edge) проверяет `@internal` по **top-level namespace**; при общем корне (`CoreX\`) граница слишком широка — нужна явная карта владения пакетов (Deptrac/кастомное правило) | `namespace-map.tsv.new_package` + правило «один `seg` — один пакет» (§5 п.3) — вход для гейта владения P03/P04 | RAG:✅ `artifacts/research-rag/pplx-10-phpstan-internal-api-attributes.md` (phpstan.org phpdocs-basics) |
| E4 | Split-репозитории — зеркала монорепо; lockstep-теги; path repositories + `self.version` только для umbrella | package-map `new_dir` = каталог split; имена composer одинаково префиксованы | RAG:✅ `artifacts/research-rag/pplx-11-monorepo-split-lockstep.md` (getcomposer.org) |
| E5 | Vendor `corex` на Packagist занят сторонним владельцем; `axiomasoft` пуст | composer-имена `axiomasoft/corex[-<name>]` (D02) | RAG:✅ `artifacts/research-rag/http-01-registry-probes.md` |

Ограничение: доступность конкретных имён `axiomasoft/corex-*` на Packagist не проверялась — vendor пуст (E5), публикация — owner-gate P09.

## Audit remediation 2026-09-27

A1–A5 исправлены по коду checkout `b624936` и локальным статическим примерам (RAG:—).
Новых внешних предпосылок не добавлено; исходные captures переиспользуются.
Проверки — `findings/design-remediation-checks.json`; независимый delta-review следует после finish.
