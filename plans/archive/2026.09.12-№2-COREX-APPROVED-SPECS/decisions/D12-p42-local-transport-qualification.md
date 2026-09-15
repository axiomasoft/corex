---
type: workflow
id: D12
date: 2026-09-14
status: accepted
item: P4.2
items: [P4.2]
supersedes: []
superseded_by: null
---
# D12 — P4.2 uses the sealed local Composer transport

**Date:** 2026-09-14
**Status:** accepted
**Actor:** plan-exec/implementation/high · gpt-5.6-terra
**Evidence:** RAG:— D11, D9, P4.1 GREEN dossier/source-map and its two reproducible candidate ZIPs. No remote repository, tag, release, credential or live operation is asserted.
**Supersedes:** —
**Superseded By:** —

## Решение

D11's explicit local-transport qualification resolves P4.2's stale availability marker for this exact local B4 invocation. The only admissible channel is a disposable isolated Composer artifact fixture built from the two P4.1 sealed ZIPs and their exact metadata. It is evidence transport, not a consumer configuration, publication, remote repository or package-release claim.

The scoped reconciliation may update only the P4.2 Files and must preserve the selected source mapping, package versions and upstream constraints. Any missing artifact checksum, source/tree mismatch, unexpected graph delta, path repository in the resulting manifest, authenticated/private requirement, or attempt to reach a remote candidate remains a hard rejection.

## Почему

P4.1 supplied the exact local candidate bytes after the original P4.2 wording was frozen. D11 expressly permits qualifying those bytes as local transport while retaining every external boundary. Treating their absent remote destination as a block would contradict D11's local-only path; treating them as published would violate it.

## Consequences

Regenerate the P4.2 bundle and use its current digests before execution. The resulting proof must state local-only proof class and leave publication/current-runner claims to their separately scoped items. This decision grants no external write, tag, fork, release, secret or private-auth read.
