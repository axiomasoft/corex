# P3 — внешняя проверка общих технических практик

RAG:✅ Только общие практики; применение к внутренней разработке — локальный вывод RAG:—.
**Perplexity:** successful
**Search artifact:** `artifacts/P3-design/perplexity-response.json`
**SHA256:** `717bd18fd64205ecc4779da0136f79142c22068a44fe4dd95f16a8aaedbac6cb`

Дополнительный transport verification: `artifacts/P3-design/perplexity-response.transport.json`,
SHA256 `a44fa9e12bfa8dbcefa33ffd88725349b4a09789870a7e3f523ee6f24589a7b4`.
Queries сохранены рядом. Initial response — failure: No answer found / changed structure;
владелец немедленно уведомлён в текущей сессии, initial query/response сохранены отдельно.
Отдельные neutral verification queries получили содержательные ответы. Initial не successful evidence.
Ни один query не передаёт имя проекта, внутренние IDs, Files, schemas или текущие gaps.

## Primary sources

Ссылки открыты непосредственно 2026-09-15; ниже краткие supported claims, не доказательство
локального кода. RFC 9110 plain URL дал Internal Error; HTML §9.2.2 успешно открыт отдельно.
Redirect Laravel docs → framework/docs сохранён в primary-verification.json. Tool response —
retrieval, два RFC одного издателя не считаются независимыми origins.

| Origin | URL | Supported claim |
|:--|:--|:--|
| Laravel | https://laravel.com/docs/12.x/filesystem | Scoped disk prefixes paths; S3 SDK separate; native Filesystem stays reusable |
| Flysystem | https://flysystem.thephpleague.com/docs/adapter/path-prefixing/ | Adapter decorator provides structural prefix, not authorization |
| PostgreSQL | https://www.postgresql.org/docs/current/ddl-constraints.html | Composite unique/PK constrain concurrent durable key identity; NULL semantics explicit |
| PostgreSQL | https://www.postgresql.org/docs/current/explicit-locking.html | Row locks serialize writers until transaction end; advisory locks cooperative |
| Redis | https://redis.io/docs/latest/develop/clients/patterns/distributed-locks/ | TTL is bounded lease; stale holder/clock/failover require fencing at protected resource |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html | Absolute expiry and invalidation server-side; client cookie alone insufficient |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html | JWT validation configured by recipient, verify issuer/audience/time; logout changes authority |
| IETF | https://www.rfc-editor.org/rfc/rfc8705 | AS client authentication and resource certificate-bound token verification distinct |
| IETF | https://www.rfc-editor.org/rfc/rfc8725 | Fixed algorithms, trusted issuer/key provenance, audience and token-kind separation |
| IETF | https://www.rfc-editor.org/rfc/rfc9110.html#section-9.2.2 | Non-idempotent ambiguous operation cannot blindly retry without semantic guarantee |
| Laravel | https://laravel.com/docs/12.x/container | Scoped instances reset per request/job; singleton context may outlive lifecycle |
| Laravel | https://laravel.com/docs/12.x/database | Transactions and database connection selection are explicit application mechanisms |

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| Existing filesystem + per-call context check | Laravel + Flysystem | Retained handle A→B must deny; prefix alone insufficient; own source tests required |
| SQL claim and durable pair/fence | PostgreSQL + Redis; Laravel transaction composition | Composite identity, current fence and final SQL atomicity must be demonstrated |
| Authenticated transport and uncertain revoke | IETF + OWASP | Exact negotiated profile/DI and deployed PKI are owner receipts, not RFC facts |
| Lease/final session write/invalidation | OWASP + PostgreSQL + Redis | Conservative elapsed source, atomic checks and rollback need actual adapter proof |
| Worker/cache/maintenance separation | Laravel + OWASP + PostgreSQL | Tenant connection teardown, cache never grants; real backup restore is separate |

## Alternatives

- Native filesystem/decorator versus second storage API: retain TenantStorage; no new filesystem contract.
- Read-then-write/Redis-only claim versus SQL key+transaction: choose durable SQL invariants; Redis coordination optional.
- Disposable reference versus supported physical mapping: qualify reference, adapt hidden implementation only after exact storage/schema receipt; no automatic registration.
- Middleware-only/session-cookie deletion versus final-write atomic fence+lease: choose both admission and final-write enforcement; unsupported entrances explicitly absent.
- Menu cache as authority versus versioned display cache + fresh click check: choose display-only; Redis loss recomputes, never grants.
- Generic JWT/mTLS profile versus hardcoded product URI/IdP: retain typed client port, negotiate exact external profile separately.

## Cross-phase impact

P2 consumes stable P3 signatures/DDL proposals when assembling exact catalogue; P3 execution consumes
accepted actual P2 catalogue, not a future runtime output. P3 storage/maintenance branch unblocks P4/P6
file IO independently of membership target deployment. P9 cold installs same adapters in non-CRM
consumer. P10 owns effective profile, auth entrances, clocks, scheduler/witness/provider IAM and all
34 DI/13 AP; P11 requires resulting release+consumer coverage. Full design-finish D4 remains pending.

## Limits / unresolved external premises

No RFC prescribes the internal 45/5/10/30s profile or proves p99.9/rotation/restore. Those obligations
stay verbatim in research/03-gates.md. Physical mapping, real provider credentials, negotiated URI/
issuer/JWKS/certificate registration and platform suspend-inclusive elapsed bounds await scoped
owner receipts at execution. Source/test existence is not executed proof; no production claims here.
