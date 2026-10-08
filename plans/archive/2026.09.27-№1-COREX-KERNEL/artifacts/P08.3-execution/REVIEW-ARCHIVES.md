The native read-only review snapshots are stored verbatim in `review-snapshot.tar.gz` (full round 1) and `review-round2.tar.gz` (bounded correction). Their embedded manifests preserve the source hashes that were reviewed. Archive hashes and byte equality against the original snapshots are recorded in `review-archive-witness.json`.

The directly readable snapshot copies remain available, with trailing whitespace normalized in three copied files for repository whitespace checks. These copies are for reading only: their source hashes and unified-diff context bytes may differ from the reviewed originals. The archives are authoritative.

To inspect exact bytes or replay locally, extract each archive into this directory; the original native prompt paths and snapshot layout will be restored. Verdicts, route proofs, actual check logs and completion notes stay directly readable.
