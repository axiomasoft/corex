---
id: D134
date: 2026-09-27
status: accepted
item: P15.11
items: [P15.11]
supersedes: [D89]
superseded_by: null
---

# D134 — G-PKG-API accept for the API package identity

**Actor:** owner / human, recorded by plan-run / grok-4.7/high

**Evidence:** owner message «делай, я подтверждаю» after the P15.11 stop on `AUTHORITY_BOUNDARY`.

## Решение

[OWNER-GRANT:G-PKG-API] branch=`main`; scope=`packages/api -> packages/operations and corex/api -> corex/operations`; owner-message=`brief/owner-invocation-p1511-grok.md#invocation-p1511-grok`

## Почему

The owner accepted the D89 rename. Composer package id, directory and split identity become `corex/operations` / `packages/operations`. `CoreX\Api`, config keys, route prefixes and operation identifiers stay. No Composer `replace`.

## Consequences

D89 is superseded. P15.11 performs the directory and Composer/split identity rename only. PHP namespace `CoreX\Api` and wire identifiers stay. P16.4 reads the resulting `packages/operations` path.
