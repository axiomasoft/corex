# Headless installed qualification — 2026-09-15

Candidate: `736c319a7d310582a1b28da92f537e4c17524b82`.

`verify-installed.py --ref 736c319a7d310582a1b28da92f537e4c17524b82`
created deterministic ZIP dists from the Git tree and installed them through
Composer into two new temporary consumers. Neither consumer used a path
repository, source checkout vendor directory, or symlink. Both produced the
same lock hash and loaded the CoreX providers from their own installed vendor
trees; the probe checked panels, media, wakeup and notification module
manifests. The scanner rejected any `FlexCRM` literal under installed `corex/*`.

Evidence: `evidence/installed.json`, SHA-256
`3754339b3e3ccbb0f0f3058944707c466826b857ec3b685a701ab6f075834bc4`.

This is local installed-candidate evidence only. It is not a publication,
release tag, hosted CI run, deployment, provider credential proof, or product
integration claim.
