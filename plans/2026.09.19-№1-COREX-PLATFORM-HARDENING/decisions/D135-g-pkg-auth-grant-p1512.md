---
id: D135
date: 2026-09-27
status: accepted
item: P15.12
items: [P15.12]
supersedes: [D90]
superseded_by: null
---

# D135 — G-PKG-AUTH accept for the Auth package identity

**Actor:** owner / human, recorded by plan-run / grok-4.7/high

**Evidence:** owner message «делай, я подтверждаю» after the P15.12 stop on `AUTHORITY_BOUNDARY`.

## Решение

[OWNER-GRANT:G-PKG-AUTH] branch=`main`; scope=`packages/auth -> packages/access and corex/auth -> corex/access`; owner-message=`brief/owner-invocation-p1512-grok.md#invocation-p1512-grok`

## Почему

The owner accepted the D90 rename. Composer package id, directory and split identity become `corex/access` / `packages/access`. `CoreX\Auth`, config keys and database prefixes stay. No Composer `replace`.

## Consequences

D90 is superseded. P15.12 performs the directory and Composer/split identity rename only. PHP namespace `CoreX\Auth`, config keys and database prefixes stay.
