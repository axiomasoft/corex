# SKM diagnostic — 2026-09-17

**Scope:** CoreX SKM/SWISSKNIFEMAN integration; this is operational evidence, not a P6 acceptance receipt.

## Commands and result

| Command | Result |
|:--|:--|
| `swissknifeman doctor` | PASS: skiller, harness, maind and analyst completed their checks. |
| `swissknifeman status corex --json` | PASS: `project:corex` is connected; memory, graph and docs watcher are reachable. |
| `swissknifeman maind health --project corex` | ATTENTION: memory hook repaired; MCP configuration hygiene still fails, as detailed below. |
| `swissknifeman maind sync --project corex --only instructions --mode repair --dry-run` | ATTENTION: two managed instruction targets are non-green. No write was made. |
| `swissknifeman maind sync --project corex --only mcp --mode repair --json` | ATTENTION: both provider targets are unmanaged and diverged; SKM safely made no write. |

## Repair performed

`swissknifeman maind onboard corex --memory maind --namespace axioma` set the memory hook to
`MODE=maind`. The subsequent health check passed both the hook check and the memory
write→recall→forget round trip.

## Remaining owner actions

1. `.mcp.json` differs from `maind.provider-deploy.claude-code` and
   `.codex/config.toml` differs from `maind.provider-deploy.codex`. D92 intentionally refuses
   to overwrite either unmanaged target. Reconcile and explicitly adopt the desired provider
   contents, then run `swissknifeman maind sync --project corex --only mcp --mode repair` to
   emit the portable `.mcp.json.example`.
2. Managed instruction synchronization is non-green for the root and package instruction
   targets. It is recorded as an operational configuration issue and does not establish or
   invalidate any product acceptance claim.

## Isolated search-service check

The D40-authorized local image
`getmeili/meilisearch:v1.53.2@sha256:8d9bce7a169fd575290cee1ec2803315afbbb8dfa50122fb10d807ad393005aa`
was started only on `127.0.0.1:7700`. `composer --working-dir=packages/search-meilisearch
test:meilisearch` passed (1 test, 3 assertions). The `corex_meili_p66` container was removed
immediately afterwards. This is local adapter evidence only, not a P6 branch receipt.

## Global, non-CoreX warnings

The fan-out also reported 20 long downstream `CLAUDE.md` files, 19 projects with managed-skill
drift, and 6 projects with duplicate skill channels. These are fleet-level warnings; CoreX is
not named as a failing target by `swissknifeman status corex`.
