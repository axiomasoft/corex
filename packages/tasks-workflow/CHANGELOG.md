# Changelog

## CoreX 2.0

- Add an opt-in Task completion contributor that maps the Tasks-owned trigger to a pinned Workflow definition and version.
- Validate the exact definition and version through Packman’s composed activation gate before enabling Tasks, preserving the host policy and current tenant engine.
- Preserve activation validation across scoped host policy resets and validate cold declarations before resolving tenant-only policies or engines.
- Allow catalogue metadata reads before provider boot completes. Compose the final host gate on its first public resolution after all providers have booted, and keep retained readers bound to the current scope. Keep shared host cache slots occupied during decoration so Laravel refresh subscribers preserve native gate lifetimes.
- Preserve the current host gate chain when workers retain registry readers across workspaces and provider boot decorators.
- Keep the host gate cache and mutable singleton state intact while retiring early diagnostics readers.
