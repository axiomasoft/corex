# Definition of Done 1.0

Generated from `scripts/dod-1.0.json` by `python3 scripts/dod-1.0.py render`.
Hand edits fail `render --check`.

Provenance: 25 audit assertions (DOD-01–DOD-25) plus 2 plan assertions
(DOD-26–DOD-27), decision D95. A score is not a pass. Exactly one evidence
mode is active on each row.

| ID | Assertion | Owner | Mode | Evidence | Negative fixture |
|:--|:--|:--|:--|:--|:--|
| DOD-01 | Every live package has a one-sentence purpose. | `P1.2`, `P17.8` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-scorecard-docs-receipt.json` | `missing-purpose` |
| DOD-02 | Package taxonomy is machine-checked. | `P3.3` | `live-command` | `php scripts/package-graph-gate.php` | `taxonomy-drift` |
| DOD-03 | `corex/core` is foundation-only, not a capability warehouse. | `P6.1` | `live-command` | `php scripts/package-graph-gate.php` | `foundation-warehouse` |
| DOD-04 | Every public symbol is present in the declared API map. | `P1.2`, `P3.3` | `live-command` | `php scripts/package-graph-gate.php` | `missing-api-symbol` |
| DOD-05 | Cross-package imports of implementation internals are forbidden. | `P3.2` | `live-command` | `php scripts/package-graph-gate.php` | `internal-import` |
| DOD-06 | Every owned table has one declared owner. | `P2.3`, `P3.5` | `live-command` | `php scripts/table-ownership-gate.php` | `table-owner` |
| DOD-07 | Configuration is validated and injected as typed options. | `P7.11` | `live-command` | `php scripts/config-composition-gate.php` | `config-injection` |
| DOD-08 | Contextual state is safe across Octane/request/queue boundaries. | `P4.7`, `P11.3` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-consumer-receipt.json` | `context-boundary` |
| DOD-09 | Durable messages are scalar-serializable and versioned. | `P5.11` | `live-command` | `php vendor/bin/pest packages/core/tests/Unit/MessagingEnvelopeTest.php --exclude-group=pg` | `message-version` |
| DOD-10 | Attributes do not depend on model/table identity. | `P10.8` | `live-command` | `php scripts/attributes-architecture-gate.php` | `attribute-identity` |
| DOD-11 | Attribute definition source is replaceable through a public contract. | `P10.8` | `live-command` | `php scripts/attributes-architecture-gate.php` | `attribute-source` |
| DOD-12 | Transfer proves resume, idempotency and partial-failure behavior. | `P12.8` | `live-command` | `php vendor/bin/pest packages/transfer/tests/Unit/CodecTest.php --exclude-group=pg` | `transfer-resume` |
| DOD-13 | Search has at least two independently tested provider implementations. | `P12.5` | `live-command` | `php vendor/bin/pest packages/search/tests/Unit/SearchServiceProviderTest.php --exclude-group=meilisearch` | `search-providers` |
| DOD-14 | Meilisearch is optional. | `P17.6` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-profile-receipt.json` | `omitted-meilisearch-profile` |
| DOD-15 | Search Intl is removable. | `P17.6` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-profile-receipt.json` | `omitted-search-intl` |
| DOD-16 | Bridge packages own no foreign data. | `P2.3`, `P3.5` | `live-command` | `php scripts/table-ownership-gate.php` | `bridge-ownership` |
| DOD-17 | Large packages match their bounded-context-first structural profile. | `P16.11`, `P9.8` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P16-profile-a-receipts.md` | `omitted-profile-b` |
| DOD-18 | Service providers remain composition roots. | `P8.6` | `live-command` | `php scripts/provider-size-gate.php` | `provider-composition` |
| DOD-19 | FlexCRM and an independent non-CRM/CMS/Store consumer use the same public lower-level contracts. | `P17.5` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-consumer-receipt.json` | `shared-contract` |
| DOD-20 | Custom external extensions pass the reusable contract suite. | `P17.2` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-extension-receipt.json` | `extension-contract` |
| DOD-21 | Upgrade from every applicable previous supported package version is reproduced. | `P17.7` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-upgrade-receipt.json` | `stale-receipt` |
| DOD-22 | PHP/API/schema compatibility diff is part of the release gate. | `P3.4`, `P17.1` | `live-command` | `bash scripts/bc-check.sh` | `bc-unclassified` |
| DOD-23 | Accepted architecture decisions and current code do not diverge. | `P15.13` | `live-command` | `php scripts/check-api-contract.php` | `decision-drift` |
| DOD-24 | Examples use only supported public API. | `P13.12`, `P17.8` | `live-command` | `php scripts/package-docs-gate.php --json` | `public-api-example` |
| DOD-25 | Every package README covers minimal install, configuration, extension and failure semantics. | `P17.8` | `live-command` | `php scripts/package-docs-gate.php --json` | `readme-contract` |
| DOD-26 | Every supported install profile boots from split archives in a pristine consumer. | `P17.6` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-profile-receipt.json` | `workspace-autoload` |
| DOD-27 | A useful application with no Flex/CRM/CMS/Store dependency passes its behavioral smoke suite. | `P17.5` | `candidate-receipt` | `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-consumer-receipt.json` | `consumer-smoke` |

Receipt rows bind source commit, candidate digest, environment, command exit
and freshness-input digests. `unknown`, `skipped`, `stale` and `not-run` stay red.
