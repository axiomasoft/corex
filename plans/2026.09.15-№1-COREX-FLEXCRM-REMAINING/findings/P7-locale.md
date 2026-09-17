# P7 locale and translation fallback

**Schema:** p7-utility-receipts/1
**Item:** P7.5

`corex/intl` ships frozen `LocaleResolver`/`TranslationReader`, request-scoped locale stacks, and LOC-01..09 local conformance in `packages/intl`.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| LOC-01 | R23 | bounded BCP47 allowlist and invalid tag rejection | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-02 | R23 | user→settings→app→fallback with parent negotiation | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-03 | R23 | scoped locale stack restores after exception | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-04 | R23 | deterministic policy hash, no remote calls | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-05 | R23 | explicit translation fallback order | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-06 | R23 | module-declared localized fields only | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-07 | R23 | existing translator adapter reused | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-08 | R23 | plain fallback when ICU disabled | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |
| LOC-09 | R23 | two fixture providers deterministic metadata | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | PASS | P7.5 | none |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen locale profile |
| `packages/intl/src/Contracts/LocaleResolver.php` | `69c709e31c525edb61e9acb40a3ac054a0085c665f3eb4921d58d543455fb837` | public locale port |
| `packages/intl/src/Contracts/TranslationReader.php` | `807399ce72406c5751f102b4dab6a80da5e8dc113e0a4ef07a9a751c2798ca50` | public translation port |
| `packages/intl/src/Internal/Locale/DefaultLocaleResolver.php` | `8af47b4e22b7f21afbbb512406d328ef48e0f323eb95e612cb51096d65bbde97` | qualified locale resolver |
| `packages/intl/src/Internal/Locale/LaravelTranslationReader.php` | `75b4783360bb7613894a8a31941d569c6b62a59d63130d21b7aa2bb31e2b66ab` | read-only translator adapter |
| `packages/intl/config/corex-intl.php` | `f97a9332587bd9202c9c421cc639e16866095e9bfa638d00a526fdf85941f134` | locale policy defaults |
| `packages/intl/tests/Locale/LocaleResolutionTest.php` | `2ac1faadb8782d5308d66879819ebb2341368efcfc87f38bc8570f4216428ff5` | LOC-01..09 sqlite proof |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| LocaleResolver | G-API | `69c709e31c525edb61e9acb40a3ac054a0085c665f3eb4921d58d543455fb837` | P7.5 | PASS | P7.8 optional bridges |
| locale/1 DTO bundle | G-SCHEMA | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | P7.5 | PASS | immutable policy hash |
