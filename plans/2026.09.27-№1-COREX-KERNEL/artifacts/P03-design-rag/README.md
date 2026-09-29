# P03 design RAG — captures index

| # | File | Tool | Topic | Used by |
|:--|:--|:--|:--|:--|
| 1 | `context7-01-pest-arch.md` | context7 (`/pestphp/docs` 5.x) | Pest arch expectations: `toBeInterfaces/Enums`, wildcards `App\*\X` in `expect()`, `toOnlyBeUsedIn` with fixed namespaces | E1 |
| 2 | `pplx-01-pest-arch-vs-layout-gate.md` | perplexity-web | Pest arch cannot express depth/sibling layout or same-module backreference; custom check recommended; PHPArkitect for broad dependency policies | E1 |

Reused without new search: `artifacts/research-rag/pplx-10-phpstan-internal-api-attributes.md` (PHPStan `@internal` works per first namespace segment → too broad for `CoreX\` monorepo; Deptrac/PHPArkitect/custom rule for package ownership).
