# P04 — верифицированные внешние факты

| ID | Факт и decision impact | Первичный источник / capture | Статус |
|:--|:--|:--|:--|
| E1 | Атрибуты ограничиваются target mask, повторение требует IS_REPEATABLE; D63 выбирает TARGET_CLASS и одиночную метку. Arguments — literals/constant expressions; поддерживаем alias и named arguments через AST name resolution. | PHP Manual [classes](https://www.php.net/manual/en/language.attributes.classes.php), [syntax](https://www.php.net/manual/en/language.attributes.syntax.php); http-01/02 | RAG:✅ |
| E2 | Roave installed CompareClasses исключает anonymous и @internal doc comments из base class list; namespace Internal сам по себе не filter. D64 выбирает marker + explicit membership, negative controls для public removal. | `vendor/roave/backward-compatibility-check/src/CompareClasses.php:32`, `:94`; frozen capture, composer.lock | RAG:— (первичный установленный код) |
| E3 | RFC9457: type identifies problem, status advisory и должен соответствовать actual HTTP status при включении; extensions допускаются. D64 создаёт уникальные type URI; string code — наша SemVer extension. | [RFC9457](https://www.rfc-editor.org/rfc/rfc9457.txt) §3.1.1/3.1.2/3.2/4; http-03 | RAG:✅ |

Raw provenance: `artifacts/P04-design-rag/README.md`; pplx-01 сам по себе не норматив.
Enum method/value(), конкретные URI, DTO поля, public verdicts, scanner shape и
порядок rollout — author choices B6/D63/D64, не приписаны внешним источникам.
Доказательство класса модели и массива потребителей — inventory, а не Perplexity.
Reflection inheritance не требуется сканеру: он читает только явные AST атрибуты;
родительская метка не распространяется на declaration ребёнка по нашей норме.
Новых dependencies/versions/tenancy-engine решений нет. Execute перепроверяет installed
parser availability и собственные scanner fixtures, не повторяет весь web search.
