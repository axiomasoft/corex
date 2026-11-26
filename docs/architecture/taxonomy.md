# CoreX package taxonomy

**Version** 1.0.0 · **Status** normative · **Level** 0 (`corex/*`)

The `extra.corex` block of a package's `composer.json` declares its architectural metadata. Articles `API-001` and `PKG-009` of the constitution govern the public surface and package boundaries. Composer retains its own package type and version.

## Composer metadata and architectural metadata

| Question | Composer metadata | CoreX metadata |
|:--|:--|:--|
| How is the package installed and discovered? | `type` (`library` or `packman-module`) | — |
| What release stage is it at? | Version, suffix and `minimum-stability` | — |
| What role does it play in the level 0 architecture? | — | `extra.corex.package-type` |
| How mature is its public surface? | — | `extra.corex.stability` |

Installation and discovery rely on the existing Composer `type` values.

## The `extra.corex` schema

```json
{
  "extra": {
    "corex": {
      "schema-version": 1,
      "module": "CoreX\\Media\\MediaModule",
      "package-type": "capability",
      "stability": "experimental"
    }
  }
}
```

| Key | Required | Type | Meaning |
|:--|:--|:--|:--|
| `schema-version` | Yes | Integer | The block's contract version, currently `1`. Readers must reject unknown versions explicitly. |
| `module` | No | String | Module class. A non-module package can omit it. |
| `package-type` | Yes | Enumerated string | Architectural role, independent of Composer's installation type. |
| `stability` | Yes | Enumerated string | Maturity of the public surface. |

Readers ignore unknown keys inside `corex`. An absent block is a distinct state. An invalid block is an error. Package metadata cannot authorize behavior prohibited by the constitution.

### Permitted values of `package-type`

| Value | Role | Intended consumers |
|:--|:--|:--|
| `foundation` | Shared primitives and contracts | All packages |
| `capability` | An independent level 0 domain capability | Products and other capabilities |
| `integration` | Adapter between packages or to an external system | Its hosts |
| `product` | Level 2 composition with product-specific semantics | The end application |
| `tooling` | Development tooling outside the product runtime | Developers |
| `fixture` | Demonstration or test consumer of the shared capability | Acceptance tests |

### Permitted values of `stability`

| Value | Consumer commitment |
|:--|:--|
| `stable` | Follow `api-policy.md`. Breaking changes require a major release. |
| `experimental` | The surface can change in a minor release. Record the change in the package CHANGELOG. |
| `internal` | Intended for use within the monorepo. |
| `deprecated` | Fixes only. Identify the replacement and the removal version. |
| `unreleased` | Outside the release set. Publication requires a separate decision. |

## Current inventory

Source attributes identify public types. `extra.corex.stability` records maturity independently. Snapshot gates check the declared surface. Verify the actual CI run, publication status and release compatibility separately under the [compatibility policy](semver-policy.md).

| Path | Composer name | Composer type | Package type | Stability |
|:--|:--|:--|:--|:--|
| `packages/access` | axiomasoft/corex-access | library | capability | stable |
| `packages/core` | axiomasoft/corex | library | foundation | stable |
| `packages/custom-fields` | axiomasoft/corex-custom-fields | packman-module | capability | stable |
| `packages/intl` | axiomasoft/corex-intl | packman-module | capability | stable |
| `packages/media` | axiomasoft/corex-media | packman-module | capability | stable |
| `packages/notifications` | axiomasoft/corex-notifications | packman-module | capability | stable |
| `packages/operations` | axiomasoft/corex-operations | library | capability | stable |
| `packages/panels` | axiomasoft/corex-panels | packman-module | capability | stable |
| `packages/recurrence` | axiomasoft/corex-recurrence | library | capability | stable |
| `packages/search` | axiomasoft/corex-search | packman-module | capability | stable |
| `packages/search-intl` | axiomasoft/corex-search-intl | packman-module | integration | stable |
| `packages/search-meilisearch` | axiomasoft/corex-search-meilisearch | packman-module | integration | stable |
| `packages/tasks` | axiomasoft/corex-tasks | packman-module | capability | stable |
| `packages/recurring-tasks` | axiomasoft/corex-recurring-tasks | packman-module | integration | stable |
| `packages/tenancy` | axiomasoft/corex-tenancy | library | capability | stable |
| `packages/tools` | axiomasoft/corex-tools | library | capability | stable |
| `packages/import-export` | axiomasoft/corex-import-export | packman-module | capability | stable |
| `packages/scheduler` | axiomasoft/corex-scheduler | packman-module | capability | stable |
| `packages/webhooks` | axiomasoft/corex-webhooks | library | capability | stable |
| `packages/workflow` | axiomasoft/corex-workflow | packman-module | capability | stable |
| `products/flex-crm` | flex/crm | packman-module | product | experimental |

The [public API reference](../reference/public-api.md) is derived from source.

The `tools` and `webhooks` packages extend Operations and can depend on `axiomasoft/corex-operations` without forming a separate architectural level. The kernel owns `OperationHandler`, `RequestContext`, `Principal` and `InvocationMetadata`. The invocation contracts are in `CoreX\Invocation`; the principal contract is in `CoreX\Context`. The product `flex/crm` can therefore declare operations using only its kernel dependency. The package graph gate prohibits a production edge from `flex/crm` to `axiomasoft/corex-operations`. See the [kernel invocation decision](decisions/2026-10-08-invocation-kernel-contract.md).
