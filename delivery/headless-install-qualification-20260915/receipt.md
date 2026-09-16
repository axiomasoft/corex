# Headless installed qualification — 2026-09-15

Candidate: `fd0110e2e6fc4e9658d5c2d5367a5b6cc4782977`.

`verify-installed.py --ref fd0110e2e6fc4e9658d5c2d5367a5b6cc4782977`
created deterministic ZIP dists from the Git tree and installed them through
Composer into two new temporary consumers. Neither consumer used a path
repository, source checkout vendor directory, or symlink. Both produced the
same lock hash and loaded the CoreX providers from their own installed vendor
trees; the probe checked panels, media, wakeup and notification module
manifests. The scanner rejected any `FlexCRM` literal under installed `corex/*`.

Evidence: `evidence/installed.json`, SHA-256
`5aa4cbac9c5a7da694d875c941acface9401414772ab39d653704f99a2d36aa1`.

This is local installed-candidate evidence only. It is not a publication,
release tag, hosted CI run, deployment, provider credential proof, or product
integration claim.
