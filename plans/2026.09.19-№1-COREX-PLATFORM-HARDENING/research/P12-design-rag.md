# P12 — RAG и внешние подтверждения

**Снято:** 2026-09-20. **Метод:** Perplexity MCP по трём узким
вопросам, затем direct-check official primary sources. Точные
запросы, границы ответов и transport defects сохранены в
`artifacts/P12-design-rag/`.

| Query | Verdict | Влияние на дизайн |
|:--|:--|:--|
| P12-Q1 errors | confirmed | RFC 9457 не заменяет package-owned stable code; human detail не парсится. Wire projection остаётся P13. |
| P12-Q2 observability | confirmed | Async causality — propagated context + link at span creation; messaging semconv Development; metric labels bounded. |
| P12-Q3 impersonation | partially confirmed | Dual identity, source identity, expiry/revocation have provider evidence. No nesting, CoreX write policy and cleanup are explicit local decisions. |
| P12-Q4 direct check | confirmed with transport defect | Official pages confirm accepted facts; generic web connector failure is disclosed and not green. |

## Primary sources

- RFC 9457 Problem Details: https://www.rfc-editor.org/rfc/rfc9457
- HTTP Semantics: https://www.rfc-editor.org/rfc/rfc9110
- OTel messaging spans: https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/
- OTel messaging metrics: https://opentelemetry.io/docs/specs/semconv/messaging/messaging-metrics/
- OTel Trace API: https://opentelemetry.io/docs/specs/otel/trace/api/
- OTel Logs data model: https://opentelemetry.io/docs/specs/otel/logs/data-model/
- OTel semantic-convention stability: https://opentelemetry.io/docs/specs/semconv/
- PSR-3: https://www.php-fig.org/psr/psr-3/
- GCP service-account impersonation: https://cloud.google.com/iam/docs/service-account-impersonation
- AWS temporary-credential revocation: https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_temp_control-access_disable-perms.html

## Граница подтверждения

Источники не определяют CoreX namespaces, error codes, HTTP map,
metric names, retry buckets, item decomposition и storage audit context. Это
локальные D60–D64. Perplexity summary использовано как retrieval lead,
а не как self-verifying authority.
