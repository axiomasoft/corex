# P12-Q2 — OpenTelemetry and PSR-3 constraints

**Tool:** Perplexity MCP. **Date:** 2026-09-20.

## Query

> Answer in at most 700 words using current official primary sources only.
> Which OpenTelemetry and PSR-3 facts constrain a vendor-neutral operation
> observer in PHP? Cover span links for asynchronous producer and consumer work,
> Stable versus Development messaging conventions, log and trace correlation,
> and metric cardinality with attribute allow-lists. Separate source facts from
> design inference and include exact URLs.

## Result used

- OpenTelemetry messaging semantic conventions are Development. A producer must
  attach creation context to the message carrier; producer and consumer work are
  connected with links by default, including per-message links for batch work.
- Links are supplied when a span is created and can affect sampling; a later
  synthetic link or a trace id derived from a business correlation id is invalid.
- Semantic-convention stability is explicit. A library must not silently change
  emitted schema/names as dependencies move.
- The OTel log model has dedicated TraceId/SpanId/TraceFlags correlation fields.
- PSR-3 accepts structured context; static message templates keep aggregation
  stable and exception data belongs in context only when intentionally exposed.
- Messaging metrics guidance prefers bounded attributes such as destination
  templates and error types. Unbounded identifiers create uncontrolled series.
- Design inference accepted for CoreX: vendor-neutral events, a closed metric
  allow-list, business IDs only in logs/traces, W3C context in P5 metadata, an
  optional OTel adapter and a pinned Development-semconv mode.

## Primary sources returned

- https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/
- https://opentelemetry.io/docs/specs/otel/trace/api/
- https://opentelemetry.io/docs/specs/semconv/general/semantic-convention-groups/
- https://opentelemetry.io/docs/specs/semconv/
- https://opentelemetry.io/docs/specs/otel/logs/data-model/
- https://www.php-fig.org/psr/psr-3/
- https://www.php-fig.org/psr/psr-3/meta/
- https://opentelemetry.io/docs/specs/semconv/messaging/messaging-metrics/

No source defined the CoreX event fields, metric names or retry buckets; those are D62–D63.
