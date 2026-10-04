---
id: D18
date: 2026-09-27
status: accepted
item: P01
items: [P01]
supersedes: []
superseded_by: null
---
# D18 — ci-supply-chain (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Все actions/reusable workflows — full SHA + комментарий версии; `permissions: {}` на уровне workflow и минимальные job-level гранты; `zizmor` блокирующий; Dependabot (`github-actions`, `composer`); `composer validate --strict` + `composer audit --locked` для каждого installable корня; CODEOWNERS на `.github/**`; SBOM (CycloneDX) — в release-job.

## Why

F00 A12.3–A12.5; `pplx-05` (GitHub hardening, zizmor, pinact, per-package audit).

## Consequences

P01.
