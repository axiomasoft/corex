# CoreX documentation

CoreX is a modular Laravel foundation for building products (for example
FlexCRM) on shared, strictly versioned packages: tenant context, access
control, schema, auditing, messaging, tasks, search, webhooks and more.

| Guide | What it covers |
| --- | --- |
| [Installation](installation.md) | Requirements, the pinned ecosystem dependencies, installing into an application |
| [Architecture](architecture.md) | Package layers, contexts and work scopes, schema, errors, extension points |
| [Configuration](configuration.md) | How configuration is published and where the generated key reference lives |
| [Usage](usage.md) | The supported PHP API and the common integration calls |
| [Errors](errors.md) | Typed error codes, problem definitions and how failures reach the wire |
| [Testing and CI](testing-ci.md) | Running the suites locally, PostgreSQL lanes, the CI job graph |
| [Proposals](proposals/roadmap.md) | Open improvement proposals with priorities |

## Reference

- [Generated configuration reference](architecture/configuration.md)
- [Error catalogue](reference/errors.md)
- [Public PHP API](reference/public-api.md) and [entrypoints](reference/public-entrypoints.md)
- [Package map](architecture/package-map.md)
- Per-package READMEs and CHANGELOGs under `packages/*/` and `products/*/`

The normative rules (invariants, ownership, naming, schema and message policy)
live in [`architecture/`](architecture/README.md).
