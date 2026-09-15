# Distribution dry-run and operator boundary

This checklist is for the four public CoreX packages only. It is a local
preflight document, not permission to create repositories, push refs, create
tags/releases, register Packagist packages, or create/inject credentials.

## Local-only verification

Run from the authoritative monorepo checkout:

```bash
python3 scripts/distribution/export.py --check
python3 scripts/distribution/pristine-proof.py --package corex/core --laravel 12
```

The exporter reads the committed source pinned in
`scripts/distribution/export-policy.json`; it does not export the dirty
checkout. Its output must match the source, package-tree, archive and metadata
fingerprints recorded in the P5.2 dossier. A mismatch is a NO-GO.

## Required separate authority for each resource

For each of `axiomasoft/corex-core`, `axiomasoft/corex-modules`,
`axiomasoft/corex-tasks`, and `axiomasoft/corex-tenancy`, a later operator
packet must name the exact resource, source commit/tree, release version/tag,
artifact checksum, operation and operator identity. It must separately grant
only the needed operations:

1. Create the mirror, only when an authenticated live read proves it absent.
2. Push the exact immutable split ref/tree.
3. Create the exact approved tag and release artifact.
4. Register Packagist or provision a secret, only when those operations are
   independently listed.

Before every external write, reconcile the real repository, visibility,
permissions and destination ref. A name collision, missing right, unknown
state or different immutable ref stops that resource. Never force-push, delete
a tag/mirror, or reuse a stale approval.

## Recovery

Before any external action, discard local staging on a failed local check. For
an already published defect, issue a new approved release; do not rewrite or
delete a published immutable tag. No credential value belongs in this document,
the dossier, the journal or a receipt.
