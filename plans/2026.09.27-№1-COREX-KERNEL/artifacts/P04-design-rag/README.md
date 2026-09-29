# P04 — retrieval provenance

Дата: 2026-09-29. Синтез и границы доверия — `research/P04-design-rag.md`.

| Capture | Tool / source | Вопрос |
|:--|:--|:--|
| `pplx-01-attributes-errors-roave.md` | perplexity-web search, raw JSON | атрибуты, Roave exclusions, RFC9457 |
| `http-01-php-attributes.html` | urllib GET https://www.php.net/manual/en/language.attributes.classes.php | ограничения/повторение атрибутов |
| `http-02-php-syntax.html` | urllib GET https://www.php.net/manual/en/language.attributes.syntax.php | имена/аргументы/константы |
| `http-03-rfc9457.txt` | urllib GET https://www.rfc-editor.org/rfc/rfc9457.txt | type/status/title и extensions |
| `roave-CompareClasses.php.txt` | installed primary source (composer.lock pinned) | реальный @internal filter |

Context7 tool не предоставлен текущему root; для PHP/RFC он не нужен, Roave проверен
по установленному первичному коду и lock. Поиск не является доказательством. Blanket
утверждение perplexity о PHP8.5 оставлено ненормативным; целевой код проверяется на PHP8.4.
Предыдущие captures `artifacts/research-rag/pplx-01`, `pplx-08`, `pplx-10` покрывают
Symfony BC, string error codes и PHPStan package internal semantics; новых внешних
Laravel рекомендаций фаза не принимает. Локальный AST inventory — RAG:—.
