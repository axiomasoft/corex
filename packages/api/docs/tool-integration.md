# Tool integration map

This document binds **selected P3–P7 producer contributions** to compiled `Api::tool` declarations
and records the **external control-plane ownership map** for R08. It is a qualification artifact
for `corex/api` tool discovery and guarded invocation; it does not install MCP servers, central
identity writers, or tenant auto-exposure for control-plane operations.

## External control-plane tools (R08)

Nineteen frozen names from B33 §7 and B34 §7 remain **external-owner / control-plane** only.
They appear in `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json`
as source-port/read maps. They must **not** be declared through `Api::tool` in tenant modules and
must **not** appear in `ToolCatalogue::discover` for account agents.

| Prefix | Count | Owner | Tenant exposure |
|---|---|---|---|
| `tenancy.*` | 8 | Provisioner / storage / TLS ops | none |
| `storage.*` (control-plane) | 2 | Provisioner / storage / TLS ops | none |
| `tls.*` | 1 | Provisioner / storage / TLS ops | none |
| `identity.*` (central) | 8 | Control-plane operator / central identity | none |

Root identity writers (`identity.grant_membership`, `identity.revoke_membership`,
`identity.resync_account_users`, `identity.invalidate_switcher_cache`) are never invoked from
account-scoped tool agents.

## Selected local contributions

Finite included contributions are listed in
`delivery/tool-contribution-qualification-20260915/fixtures/selected-contributions.json`.
Each row binds:

- producer receipt and local case (`findings/P3-*` … `findings/P7-*`)
- producer contract/version (`storage-context/1`, `panel/1`, `wakeup/1`, …)
- owning module composer name and canonical tool code
- side effect, idempotency, ability and **contract hash** from compiled bytes

### Archive cohort (`example/archive-tools`)

| Phase | Tool code | Producer case | Contract |
|---|---|---|---|
| P3 | `files.read_retained` | archive-lab-storage | storage-context/1 |
| P3 | `maintenance.export_account` | archive-lab-maintenance | claim-maintenance/1 |
| P3 | `identity.read_context` | archive-lab-identity | identity-transport/1 |
| P3 | `accounts.list_menu` | archive-lab-account-list | account-list/1 |
| P4 | `panels.render_slot` | PAN-01 | panel/1 |
| P5 | `workflow.wakeup_local` | WAK-01 | wakeup/1 |
| P6 | `search.query` | SRCH-01 | search/1 |

### Lab cohort (`example/lab-tools`)

Disjoint from archive; proves install optionality without every unselected sibling.

| Phase | Tool code | Producer case | Contract |
|---|---|---|---|
| P7 | `locale.format` | LOC-01 | locale/1 |
| P7 | `fx.convert` | FX-01 | fx/1 |
| P7 | `geo.resolve` | GEO-01 | geo/1 |
| P7 | `tasks.complete` | REC-01 | recurrence/1 |

## Consumer obligations

- **Discover/invoke authority** stays in consumer `ToolPolicy`; this map is not a grant.
- **Mutations** require consumer `ToolExecutionGuard` with domain-key or provider-key semantics
  from P8.4; default `ReadOnlyToolExecutionGuard` admits reads only.
- **Missing producer** or unselected contract → tool unavailable; no inference from convenient
  CRUD, UI, recipients, CRM booking defaults, or RUB/FX fallbacks.
- **Transport/live/central proof** (MCP routes, PAT/OAuth, fleet DNS/TLS/IAM) belongs to P9/P10/P11
  owners; generic parity here does not claim deployed control-plane completion.

## Cold install and transport parity

- `delivery/tool-install-qualification-20260915/` archives `corex/core`, `corex/modules`, and
  `corex/api` as immutable ZIP dists for two neutral consumers (`verify-installed.py`).
- `Internal\McpToolsProjection` implements fake in-memory `mcp-tools-projection/1`
  (`MCP2025-11-25`) over the same `ToolCatalogue` and `ToolInvoker` contracts.

## Qualification tests

`packages/api/tests/Feature/ToolContributionsTest.php` exercises CONTRIB-01–CONTRIB-08 against
the fixtures above using neutral `ArchiveToolModule` and `LabToolModule` wrappers.

`packages/api/tests/Feature/InstalledToolContractTest.php` exercises INST-02–INST-05 cold
consumer policy, MCP projection parity, provider isolation, and archive/lab cohort optionality.
