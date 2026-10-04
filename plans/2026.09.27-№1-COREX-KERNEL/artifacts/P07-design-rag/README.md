# P07 design — raw captures

Raw provenance for `research/P07-design-rag.md`. Data, not authority.

| File | Tool | Date | Topic | Used by |
|:--|:--|:--|:--|:--|
| `http-01-packagist-stancl-azguard.md` | curl, Packagist v2 API (primary) | 2026-10-04 | stancl/tenancy and jobpipeline versions/licenses/requirements; AzGuard absence | W1, W2, W7 |
| `pplx-01-stancl-v4-status.md` | perplexity-web | 2026-10-04 | v4 status, license, 3.10 Laravel 12/13 | W1 |
| `pplx-02-custom-db-per-tenant.md` | perplexity-web | 2026-10-04 | own DB-per-tenant switching, queue context, cache, Octane | W3–W5 |
| `pplx-03-support-impersonation.md` | perplexity-web | 2026-10-04 | support impersonation grants, dual audit, profiles | W6 |

Reused captures (no re-search): `artifacts/research-rag/pplx-04-stancl-tenancy-v4.md`, `pplx-06-stancl-v4-availability.md`, `http-01-registry-probes.md` (2026-09-27 baseline); `artifacts/P05-design-rag/` (resident mode, D70).

## D100–D102 corrective provenance

No new external decision was introduced by the six-finding repair or file-session/CLI-configuration and independent delta-review corrections. Sources are installed product/vendor/gate code and the local Grok parser/inspect metadata (RAG:—); existing W1–W7 captures remain authoritative for external premises. Reproducible evidence is in ../P07-design-remediation/ and ../P07-grok-rereview/.
