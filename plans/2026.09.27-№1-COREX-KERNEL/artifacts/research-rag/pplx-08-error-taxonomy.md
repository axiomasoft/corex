# pplx-08 — Таксономия ошибок: enum vs string registry, RFC 9457

- Tool: perplexity-web `search` · Date: 2026-09-27
- Query: "PHP error taxonomy design for a platform API: stable machine-readable error codes as backed enum vs string registry with namespaced codes (e.g. \"tool.unavailable\"), mapping to RFC 9457 problem+json type URIs and HTTP status; how do Stripe, GitHub, Shopware, API Platform and Symfony handle extensible error codes contributed by plugins?"
- Status: provenance; RFC 9457 — первичный источник datatracker.

## Answer (verbatim, condensed whitespace)

Короткий вывод: для платформенного API с кодами, которые могут добавлять плагины, не делайте backed enum единственным источником публичных кодов. Лучше использовать строковые коды и расширяемый реестр метаданных; core-значения при этом можно типизировать enum’ом или константами. RFC 9457 type URI, машинный код и HTTP status решают разные задачи — не сводите их к одному полю.

Разделить: code — стабильный машинный идентификатор (tool.unavailable, acme.shipping.rate_not_found); type — абсолютный URI типа проблемы RFC 9457; HTTP status; title/detail — человекочитаемые. `code` — extension-поле RFC 9457. Поле status должно совпадать с реальным HTTP status.

Enum или строковый registry: Backed enum — для закрытого набора кодов ядра; enum нельзя дополнить case’ами из независимо устанавливаемого плагина. Публичный wire-код — строка. Core-коды — backed enum/константы, сериализуются в строку. Plugin-коды регистрируются через контракт вроде ProblemDefinition(code, typeUri, defaultStatus, title, retryable, documentationUri). Реестр собирается при bootstrap; неизвестный код → ошибка интеграции + безопасный fallback. Конфликт кода — ошибка запуска/валидации. Core владеет зарезервированными префиксами (core.*, auth.*), плагины — собственным префиксом (vendor.plugin.* / plugin-id.*).

Отображение в API: registry хранит метаданные; default status в определении, фактический — в ошибке; type и code стабильны. Type URI — в namespace владельца. Итог: строка как wire-контракт + registry как extension point + enum/константы как удобство для core. Shopware использует доменные строковые коды; Symfony и API Platform решают маппинг исключений в HTTP.

## Sources
1. https://datatracker.ietf.org/doc/html/rfc9457
2. https://docs.stripe.com/api/errors
3. https://docs.stripe.com/error-codes
4. https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api
5. https://developer.shopware.com/docs/resources/guidelines/code/core/domain-exceptions.html
6. https://api-platform.com/docs/core/errors/
7. https://symfony.com/doc/7.4/reference/events.html
