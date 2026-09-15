# P2 — проверка общих практик, 2026-09-15

**Perplexity:** successful
**Search artifact:** `artifacts/P2-design/perplexity-response.json`
**SHA256:** `47f5cc274a28324cd794ef9a4f71ea5cdf6eaf12c203d525d872752fd130f692`

RAG:✅ Фактический нейтральный query/result сохранён. Предмет — общие практики разработки
Laravel-библиотек и публичные примеры, **не исследование CoreX**. D6 действует для всего плана.
Точный запрос: [perplexity-query.txt](../artifacts/P2-design/perplexity-query.txt).
Ответ сослался на Laravel 12, SemVer и RFC 8705; checked target Laravel 13 и остальные первоисточники
ниже — дополнительная прямая проверка, не утверждение, что все они появились в ответе Perplexity.
Ответ заканчивается незавершённым пунктом; принимаются только завершённые claims, проверенные
непосредственно. Полноту ответа или идеальность архитектуры не заявляем.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| Laravel | https://laravel.com/framework/docs/12.x/container | Interface/contextual DI, conditional binding; scoped instance flushing при новом request/job lifecycle. Checked directly 2026-09-15; это механика, не доказательство tenant isolation. |
| Laravel | https://laravel.com/framework/docs/13.x/container | Та же нужная DI/lifetime механика в target 13. Второй major framework — не независимый publisher. Новые version-specific attributes не выбраны. |
| SemVer | https://semver.org/spec/v2.0.0.html | Явно объявленный public API, immutable released bytes, major rule при x>0; 0.y.z имеет отдельное послабление. Local AGENTS major policy строже и не выводится из стандарта. |
| IETF / RFC Editor | https://www.rfc-editor.org/rfc/rfc8705.html | mTLS client authentication и certificate-bound access tokens различны; resource проверяет token/certificate binding, а безопасная передача certificate metadata от proxy остаётся deployment concern. |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html | Deny by default, permission validation every request, authentication отдельно от authorization. Checked directly; конкретные policy/guard/context ordering проверяются локально. |
| PHP | https://www.php.net/manual/en/language.oop5.properties.php | Typed/readonly properties, readonly не гарантирует deep immutability вложенного объекта; инварианты data shapes требуют отдельного дизайна и проверки. |
| IETF / RFC Editor | https://www.rfc-editor.org/rfc/rfc9457.html | Problem Details не требует замены существующего domain-specific error format; допускается отдельный HTTP mapping. Same publisher as RFC 8705. |
| CloudEvents project | https://raw.githubusercontent.com/cloudevents/spec/v1.0.2/cloudevents/spec.md | Versioned interoperable event representation, required context attributes и source+id identity. Не предписывает внутренний bus/engine или exactly-once delivery. Stable tag checked; dependency не выбрана. |

## Alternatives

Практики: narrow ports/contextual DI вместо universal adapter; explicit context или scoped state
вместо actor-specific singleton; typed immutable data вместо arbitrary arrays/DTO interfaces;
explicit optional absence вместо false delivered/allow; exact API/BC proposals вместо prose approval.
Laravel+OWASP поддерживают mechanics/trust premises, PHP+SemVer — typed shape/compatibility premises,
OWASP+RFC 8705 — независимость authority и credential proof. Число портов, DTO namespaces и
ownership конкретного проекта этими источниками **не определяются**.

RAG:— Локальное применение: сохранить A01–A09, existing API envelope, DomainEvent и 8 extenders;
не добавлять universal CRUD/event/auth engine. Existing stateless TrustedContextResolver не надо
переводить в scoped только из-за best-practice примера: lifetime выбирается по фактическому state.
DB-per-account и boxed уже приняты локально; поиск не выбирает tenancy и не подтверждает его устройство.
Новые ports только по точному frozen gap и independent non-CRM scenario. Product/standalone ownership
определяется doctrine/R/AC, а не категорическим веб-советом «search/notifications outside core».

## Cross-phase impact

RAG:— P2.1 квалифицирует P1 lock и existing ports; P2.2 отделяет protected identity/auth/profile proposals;
P2.3 сводит exact proposals из полного стабильного дизайна P3–P8; P2.4 seals catalogue/gate requests.
Чтение будущих **design specifications** P3–P8 при исполнении P2 не является обратной execution
зависимостью: runtime P3–P8 читает P2 produced catalogue; whole-plan-first даёт specs до P1 execution.
Non-CRM fixtures, absent-provider/default-deny/context-lifetime и error/idempotency cases становятся
acceptance contracts P3–P8; actual installed/release receipts P9, deployed DI/AP P10, consumer pins P11
не заменяются source или этим RAG. Неизвестный external target — named gate для affected membership
subset; pure recurrence/intl/headless design не ждёт deployed profile.

## Retrieval correction and limits

Два ранее отправленных query ошибочно содержали внутренний CoreX context. Сохранены initial/
superseded query/results для истории; **не используются как итоговый RAG и не доказывают CoreX**.
Первый ответ содержал out-of-scope row-level alternative и Laravel 11 link; расхождение сообщено.
Владелец исправил предмет поиска (D6); итоговый новый query нейтрален и выполнен успешно.
Нельзя объявлять internal symbols, отсутствующие возможности, выбранные package versions,
source-owner approval, deployed central profile или production acceptance проверенными вебом.
Такие факты требуют локальных source/tests/receipts или отдельного owning environment evidence.
