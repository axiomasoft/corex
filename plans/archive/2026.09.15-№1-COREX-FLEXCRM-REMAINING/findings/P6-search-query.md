# P6 search query receipt

**Schema:** search/1; p6-data-receipts/1
**Item:** P6.6

The base provider returns candidate references only. Its PostgreSQL path allowlists identifiers and text-search configuration, binds text input, uses an encrypted authenticated cursor, and never treats an index candidate as authorization. Current-row hydration is a separate policy boundary. The optional SDK is confined to `corex/search-meilisearch`; its default cold profile creates no client or network request.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| SRCH-01 | R32 | typed base boundary | invalid bounded query/filter input | query rejects malformed or over-budget input | `358a5f4e1884fbe2e4870754b11b19a6e9860e1925337de81593178aa78cccf4` | PASS | public contract evolution remains major/owner-governed |
| SRCH-02 | R32 | PostgreSQL 16 fallback | approved static schema and user text `pump` | bound `websearch_to_tsquery` returns candidate IDs | `011f2e2bcd0fdc4f94e039604d732651d723170efe99827314bc260be2f1e8c5` | PASS | rank/facet/snippet parity is intentionally unsupported |
| SRCH-03 | R32 | PostgreSQL 16 cursor | page boundary, altered scope/query cursor replay | opaque cursor advances once and rejects replay outside binding | `011f2e2bcd0fdc4f94e039604d732651d723170efe99827314bc260be2f1e8c5` | PASS | cursor is bounded to 2 KiB and provider generation |
| SRCH-04 | R32 | physical A/B PostgreSQL | separate `corex_search_a_test`/`corex_search_b_test` databases | B cannot return A row or accept A cursor | `011f2e2bcd0fdc4f94e039604d732651d723170efe99827314bc260be2f1e8c5` | PASS | test databases only; tenancy routing remains CoreX tenancy-owned |
| SRCH-05 | R32 | current policy hydration | deleted, stale-schema and denied candidates with forbidden field | only current, authorized schema-approved fields remain | `358a5f4e1884fbe2e4870754b11b19a6e9860e1925337de81593178aa78cccf4` | PASS | consumer supplies current authoritative loader/policy |
| SRCH-06 | R12 | cold optional adapter | default `corex-search-meilisearch` configuration | no SDK client, endpoint or secret boot | `a6d21dff6521e7256e5e481186633d77083d924fcc17b96b80583038f8d6a89b` | PASS | consumer must explicitly enable/configure adapter |
| SRCH-07 | R32 | D40 loopback Meilisearch v1.53.2 | indexed local documents and common text query | candidate identifiers only, no returned hit metadata | `b4126be4932ca7d34b2c42a65f7ba07953253e0e49cb09757381debed99466b7` | PASS | local disposable container removed after test |
| SRCH-08 | R32 | Meilisearch capability boundary | typed filter submitted to common adapter | unsupported capability fails closed instead of silently changing semantics | `57add574910a4b785222921b14d0e6dd9715ae5ece7a22b86dc4f566570a56da` | PASS | filters/cursors require a separately qualified adapter capability |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `decisions/D40-p6-search-adoption.md` | `f73f2eb5168f6c6292326c2997c68a719de2d845f6a250bcbd993c5d04af98aa` | scoped dependency and local-image receipt |
| `artifacts/B-P6-SEARCH/owner-adoption-receipts.json` | `b534adf9bfd4d98920dab8425adc37b815524ef2b847e930898905ef86e4fc50` | Q-P2-034–039 owner acceptance |
| `artifacts/B-P6-SEARCH/P6.6-runtime-20260917.md` | `b4126be4932ca7d34b2c42a65f7ba07953253e0e49cb09757381debed99466b7` | executed local command record |
| `packages/search/tests/Pg/PostgresSearchProviderTest.php` | `011f2e2bcd0fdc4f94e039604d732651d723170efe99827314bc260be2f1e8c5` | PostgreSQL, cursor and physical A/B proof |
| `packages/search/tests/Unit/PolicySearchHydratorTest.php` | `358a5f4e1884fbe2e4870754b11b19a6e9860e1925337de81593178aa78cccf4` | current-row policy and field proof |
| `packages/search-meilisearch/tests/Feature/MeilisearchQueryAdapterTest.php` | `57add574910a4b785222921b14d0e6dd9715ae5ece7a22b86dc4f566570a56da` | actual service and capability-denial proof |
| `packages/search-meilisearch/tests/Unit/MeilisearchServiceProviderTest.php` | `a0636474a3c9e864160ddfc52945db83f940a4a254f116ce88102216764e26e6` | cold disabled-profile proof |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| search/1 | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | D40; Q-P2-034–039 | PostgreSQL fallback and optional D40-pinned Meilisearch query adapter | SearchSchema, SearchProvider, SearchHydrator, SearchDocuments, IndexDriver | consumer owns authoritative data/policy and all production provider qualification |
