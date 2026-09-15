---
type: workflow
id: D1
date: 2026-09-12
status: accepted
item: P1.1
items: [P1.1, P1.2, P1.3, P1.4, P2.1, P2.2, P2.3, P3.1, P3.2, P4.1, P4.2, P4.3, P4.4, P4.5, P5.1, P5.2, P5.3]
supersedes: []
superseded_by: null
---
# D1 — Новый design contract на принятых owner branches

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-designer/frontier/high · gpt-5.6-sol
**Evidence:** RAG:— `brief/00-brief.md#owner-request`; predecessor handoff/queue/D11–D21, SHA256 ниже.
**Supersedes:** —
**Superseded By:** —

## Решение

Создать отдельный Layout v2 план. Accepted относится к точности design scope, не к runtime approval этого нового результата. Все 11 queue responses predecessor имеют outcome=apply в future-spec-design-only. Потребитель implementation пока не запущен; все новые items Not started. 2026.09.12-№1-COREX-SUCCESSOR P3 закрыта; predecessor files, journal, state и решения не изменять.

| Owner source | Selected branch | New spec | Source SHA256 |
|:--|:--|:--|:--|
| [D11](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D11-owner-coverage-policy.md) | effective80-target 85 | P1.2 | `57571a88d793b471ca1bf182f96ad825233d87659bdead0e160d54c8b4a189b7` |
| [D12](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D12-owner-l0-stack-policy.md) | l0-php83 | P1.3 | `c13c8801cca779501f2caa36f6bde764c6b7a0f8e4f285bc6e6c1e973bff6576` |
| [D13](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D13-owner-forward-source-corrections.md) | minimal-forward-corrections | P1.4 | `00a26d9fe4865c71fbb52477a8321959fd3bca60cc787e874fcb4f68248bace9` |
| [D14](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D14-owner-template-parity.md) | hash-amendment-spec | P2.1–P2.3 | `6bc4eef332234e2baf08177e4e079104e776b089220aae9373d26a2f6768e244` |
| [D15](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D15-owner-boxed-workspaces-deferred.md) | unsupported-until-consumer | P1.4 (docs only; adapter deferred) | `05885f09999dba3df36b0db5329c3d17c2406df820f09366efcd7212b3a83e16` |
| [D16](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D16-owner-domain-control-plane.md) | consumer-control-plane-owner | P3.1 | `ec24b26b9b5638d9de537fd6579a7b51be88efc09a4c4cb5bea8655f8caba498` |
| [D17](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D17-owner-role-write.md) | consumer-writer-owner | P3.2 | `3ccfddbc179b85a96248847daebd5797a27548ccccd77956f1a7ca92b461b459` |
| [D18](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D18-owner-public-split-topology.md) | public-candidate-split-spec | P4.3–P4.4; P5.2–P5.3 | `003e7307dfba005f4c31a45ad297d90cf25fd6d95843a24904f95f8531594b2f` |
| [D19](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D19-owner-auth-private-channel.md) | private-immutable-source-spec | P4.5 | `821c9593c594e1606fc470aea592c1d46cf2129e07ef0c3614bfcbecf90713df` |
| [D20](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D20-owner-stancl-fixed-source.md) | owned-fixed-metadata-source-spec | P4.1–P4.2 | `441b36e6de8a99194658e7b69d05640c8e94d1a9bd694eda956098e42344998a` |
| [D21](../../2026.09.12-№1-COREX-SUCCESSOR/decisions/D21-owner-compatibility-proof.md) | minimum-factual-proof-spec | P5.1 | `972b4d888d0248f6066820a84bd48a797dadafb61d686af26f331b9c361f8fa2` |

## Почему

D6–D8 — исторические evidence/proposals с прежним pending wording. D11–D21 и queue responses разрешают branch choices; копирование approvals создало бы второго владельца факта. Новый план подробно фиксирует выбранное поведение, но concrete source/schema/consumer/public/external inputs всё ещё требуются.

## Consequences

Дизайн разрешён текущим запросом. Исполнение требует отдельной routed invocation по конкретным specs и удовлетворённых gates D2. Никаких source/schema/dependency/runtime/public API или external edits в текущем design turn. BOXED-INIT не получает runtime item: D15 принимается как deferred outcome; P1.4 лишь явно документирует профиль. Core-only constant account/no-workspace сохраняется, optional workspace adapter только после named consumer и нового design scope. Auth optional/private, не default public graph; crm вне L0 allowlist. Coverage floor 80/target 85, stricter package gates сохраняются. compatibility.json tooling, Move/Rename, новые packages/extenders, year-2 features исключены. Review не выдаёт audit-admission; audit-phase не рекомендован.
