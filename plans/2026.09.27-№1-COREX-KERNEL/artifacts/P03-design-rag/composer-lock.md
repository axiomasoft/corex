# Composer lock metadata — 2026-09-29

Source: https://getcomposer.org/doc/03-cli.md#update-u-upgrade
Tool: web.run, official Composer CLI documentation; retrieved during P03 design remediation.

Relevant excerpt: “Overwrites the lock file hash to suppress warning about the lock file being out of date”

The option description also states that package versions stay fixed while metadata can refresh.
`--no-install` suppresses the install phase. This is not sufficient to guarantee our stricter contract:
`p03-transfer.py lock` checks the whole lock except local FQCN metadata and path references.
Repository evidence and the exact command output are in `artifacts/P03-design-remediation/`.
