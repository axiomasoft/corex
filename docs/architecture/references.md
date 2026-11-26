# External design references

Each entry names a specific mechanism that informed a CoreX decision and identifies where it applies.

| Project | Mechanism | CoreX application |
|:--|:--|:--|
| Symfony | Validate and compile contributions before freezing the registry. Exclude `@internal` from compatibility guarantees and give `@experimental` a limited lifetime. | EXT-004, API-002, API-005 |
| Drupal | Keep the package category in `type`, tool configuration in `extra` and a cache of plugin definitions. | [Package taxonomy](taxonomy.md), EXT-003 |
| TYPO3 | Version the extension metadata subtree in `composer.json`. | [Package taxonomy](taxonomy.md), `schema-version` |
| Laravel | Discover packages through `extra.laravel` and keep local policy in the project root. | [Package taxonomy](taxonomy.md) |
| Composer | Assign an owner to each namespace in `extra`. Express release stability in the version. | [Package taxonomy](taxonomy.md) |
| Shopware | Define the extension lifecycle in terms of installation, activation, deactivation and removal. | EXT-003 |
| Shopify | Give a component exclusive ownership of its data. Enforce cross-boundary access restrictions mechanically. | DATA-001, DATA-003 |
| Spring Modulith | A code-module boundary alone cannot isolate database access. | [Data ownership policy](data-ownership-policy.md) |
| [CloudEvents 1.0.2](https://github.com/cloudevents/spec/tree/v1.0.2/cloudevents) | Use structured JSON and give each public event type a stable `type`. Distinguish internal and external profiles. | MSG-003, MSG-004, [message policy](message-policy.md) |
| Standard Webhooks | Use a delivery identifier, verify signatures, protect against replay and support multiple signatures during rotation. | P14 |
| Stripe | Reuse an idempotency key for the same request and detect conflicting parameters. | P13 |
| Atlassian | Prefer additive changes. Let clients query capabilities explicitly. | API-006, D2 |
| GitLab | Keep a long-lived major version in the path for self-hosted deployments. | D2 |
| Grafana | Assign maturity levels to API groups. | D2 |
| Google AIP / Microsoft Graph | Model submission, polling, results, cancellation and expiry in a single long-running operation contract. | D9, P14 |
| Salesforce | Distinguish application scope from a user's record and field permissions. | P13 |
| PostgreSQL | Use a B-tree index when ordinary ordered traversal is required. | P10 |
| OpenTelemetry | Limit metric label cardinality. | P12 |
| Akeneo / Pimcore | Proposed precedent for keeping a stable attribute identity separate from its code. | P10 — `[UNVERIFIED]` |

The Akeneo/Pimcore attribution still lacks external confirmation.
