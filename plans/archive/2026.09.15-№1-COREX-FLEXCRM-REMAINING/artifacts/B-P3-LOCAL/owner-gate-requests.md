# B-P3-LOCAL — конкретный запрос недостающих owner receipts

RAG:— Предварительная read-only qualification, не execution acceptance и не issued grant.
P3.1/P3.2 остаются Not started. D23 уже принимает advisory aggregate budget; повторное решение
о бюджете или исполнении группы не требуется. P2 proposals не являются разрешениями.

## Matching receipt check

Проверены текущие decisions, P2 request index/catalogue и receipt/gate carriers в repository.
Q-P2-001/G-STORAGE и Q-P2-002/G-DEPENDENCY имеют status proposal; независимый accepted receipt
для C-P2-001 и exact immutable qualification ниже не найден. Архивные grants другого плана
(включая P3.2-SIGNUP-GATES-20260913) и install/test receipts не покрывают новый object candidate.
C-P2-002/P3.2 — no-delta, none new DDL/dependency; дополнительных blanket решений для него не запрашивается.

Binding: findings/P2-contract-proposals.md SHA256 8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b;
findings/P2-contract-catalogue.md SHA256 ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573;
research/P3-adapter-design.md SHA256 05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525;
phases/P3/P3.1.md SHA256 4c753fe31b0e6801fd7716a9f44d40e5d4209142004695cfb87a86d6a06c3796.

## Q-P3-LOCAL-STORAGE-001 — G-STORAGE, qualifies Q-P2-001

Owner: CoreX adapter maintainer + consumer schema/ops. Предлагается принять exact local library
branch tenant-storage/keep + object-adapter/1/C-P2-001: hidden ObjectTenantStorage implementing
unchanged TenantStorage; constructor (TenantContextResolver, FilesystemManager, array durableConfig,
string ephemeralRoot). Trusted server-only s3 config, canonical account UUID prefix tenants/<uuid>/,
on-demand immutable scoped adapter. Ephemeral — existing local storage. No automatic provider binding.
ScopedFilesystem additive final bool localPaths=true; object mode false refuses path()/url(), keeps
all path/context checks; existing local/boxed behavior unchanged. usage/export=false explicitly throw
StorageCapabilityUnavailable. Open streams consumed/closed within owning context, acquisition bound;
no impossible promise of revocation of an already-open OS resource.

Принять только изолированный native-SDK integration store для P3.1: существующий
MinIO RELEASE.2025-04-22T22-12-26Z image minio/minio@sha256:a1ea29fa28355559ef137d71fc570e508a214ec84ff8083e39bc5428980b015e,
local image ID sha256:9d668e47f1fc60ea49af4203deee87a657eb1aa0e2761fee2c7c2d1df282c880; loopback-only separate disposable container corex_p3_storage_test,
bucket corex-p3-storage-test, test-generated credentials, temporary data root. Logical retention —
only test run; no production data/IAM/store/restore adoption. Private visibility, per-account prefix,
A/B retained-handle deny and fault/outage no wrong-tenant fallback обязательны. Local integration
is native SDK + S3-compatible server, distinct from fake-s3 and from AWS/live provider evidence.

MinIO upstream release tag commit 0d7408fc9969caf07de6a8c3a84f9fbb10a6739e declares AGPLv3 in pinned LICENSE.
Image label vcs-ref 519a52b1bc2ef31e2633c20f34a0df840e159172 does not match the upstream tag commit; its raw GitHub
LICENSE selector returned404. Image binary qualification therefore binds exact OCI/local digest;
source-to-image reproducible build is not asserted. Local MinIO remains a separate disposable test
server, never bundled into CoreX library or used as selected production provider. This bounded test
use is part of the requested owner decision. Effective production G-STORAGE/IAM/retention/restore
receipts remain P10, no live grant requested.

## Q-P3-LOCAL-DEPENDENCY-001 — G-DEPENDENCY, qualifies Q-P2-002

Owner: package/source owner. Принять exact immutable candidate closure below for optional native
object adapter qualification and isolated tests. Direct package league/flysystem-aws-s3-v3=3.35.3;
server-selected object mode only, SDK absence denies object construction. Local/boxed consumers
retain no production requirement on SDK/credentials. Proposed packages/tenancy/composer.json delta:
optional suggest describing caller-owned adapter installation; exact direct require-dev=3.35.3 for
qualified tests. No new production require. Any different source/ref/closure needs matching receipt.
Root/package working locks are unchanged in this packet; after adoption all affected lock/input
updates require item Files ownership and fresh bounded checks, no blanket update granted.

| Package | Version | SPDX | Source commit | Downloaded dist SHA256 |
| :-- | :-- | :-- | :-- | :-- |
| aws/aws-crt-php | v1.2.7 | Apache-2.0 | d71d9906c7bb63a28295447ba12e74723bd3730e | 82c04ea2c7985f06e5a1ef4ba56a231e8ac051d490796c406c821c253f6d38be |
| aws/aws-sdk-php | 3.395.2 | Apache-2.0 | d027655c121cc80192275e72d189612efa8dd9a9 | 2b49fa24118b6fc2cc224ffb67a380449bb8b75a63d0ee96502e3346d2611f19 |
| league/flysystem-aws-s3-v3 | 3.35.3 | MIT | b03780cb97585ee7e48977f40ed599b33b751634 | 98a5831eac7034691f5d39231add19c8dff06bc7ea223461ee2f4233e5cba2de |
| mtdowling/jmespath.php | 2.9.2 | MIT | 2157c5e50e813ec6a96c1eed3be7f64a20fb32a8 | 2be72e37cd2978c34b528f3138c4e1c261b1a3ff1d075b3bd8997c5f87ba61d8 |
| symfony/filesystem | v7.4.18 | MIT | 90d412aa5277c6819db39e7605aa46b1019e3232 | 6464d956bd1472a68452e73c6ffaa7d03d0849afd166e93366e83135dd2dbd86 |

All14 shared dependencies keep current root lock versions; full19-package dependency closure,
source/dist refs, manifest/ext requirements and retained metadata/license bodies:
artifacts/B-P3-LOCAL/dependency-qualification.json / proposed-composer.lock.
Archive SHA binds actual downloaded bytes (Packagist dist shasum is empty). No package installed.
Composer2.8.3/PHP8.4.1 resolved this isolated metadata candidate with platform PHP8.4.0, no scripts/plugins;
no advisory reported in resolver output. Symfony filesystem7.4.18 (PHP>=8.2) chosen instead of8.1.6
(PHP>=8.4.1), preserving tenancy's ^8.4 floor. This solver check is not actual Laravel12/13/PHP matrix,
SDK ABI conformance or an executed upstream suite; those proofs remain mandatory before supported
label/closure and installed cohort P9. No global source or future/latest adoption authorized.

Primary practice source: [Flysystem official AWS S3 adapter](https://flysystem.thephpleague.com/docs/adapter/aws-s3-v3/).
Exact upstream declarations/licenses are archived from commit-bound dist ZIPs, inspected at metadata scope;
no full source/upstream-suite execution is claimed.

## Decision requested / exact resume

Достаточно одного owner response, explicitly accepting BOTH Q-P3-LOCAL-STORAGE-001 and
Q-P3-LOCAL-DEPENDENCY-001, bound to this packet SHA and immutable closure/service receipts.
It permits the described local implementation/test qualification and protected adoption branch;
supported result remains conditional on every P3.1 declared local proof PASS. If declined/changed,
record exact alternative or denial; native-object-sdk-isolation cannot become UNSUPPORTED/PENDING GREEN.
No other P2 proposals/API/major/DDL/source/provider/central/cloud/private/release/audit grant requested.

Resume in existing root: capture fresh five carriers, consume all Required Reads in order (current
context read is preflight only), validate source seals/decision bindings/route/batch/evidence graph,
contract pre_mutation and recovery START; then P3.1 → close → next bundle/admission → P3.2.
After P3.2 prepare B-P3-IDENTITY handoff; leave P3 open and stop at P3.2. Current owner scope continues,
no renewed group execution approval. Product GREEN reports do not exist yet.
