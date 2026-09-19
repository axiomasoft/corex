# P7 optional intl/search bridges

**Schema:** p7-utility-receipts/1
**Item:** P7.8

`corex/intl` exposes the optional `AddressSuggestProvider` port with bounded query/result contracts and offline fixture/configured drivers. `corex/intl-search` adds disabled-by-default localized search schema/hydration glue and saved-view label metadata reads over existing P6/P4 seams without introducing a P6→P7 runtime cycle.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| OPT-01 | R25 | absent search/address integrations stay offline at boot/query | `a527a8479b72705d9bb238d67ae91bab636b11896092a71234726a8d8c5eac91`; `d727e4cef0d0272cb98066fa749afc02ee697597d159e46331218563314bc9aa` | PASS | P7.8 | none |
| OPT-02 | R23 | localized searchable columns projected without Meili-only attributes | `d727e4cef0d0272cb98066fa749afc02ee697597d159e46331218563314bc9aa` | PASS | P7.8 | none |
| OPT-03 | R23 | saved-view labels resolved as metadata only | `d727e4cef0d0272cb98066fa749afc02ee697597d159e46331218563314bc9aa` | PASS | P7.8 | none |
| OPT-04 | R25 | address suggest enforces bounds and distinct statuses | `a527a8479b72705d9bb238d67ae91bab636b11896092a71234726a8d8c5eac91` | PASS | P7.8 | none |
| OPT-05 | R25 | two interchangeable address drivers via same port | `a527a8479b72705d9bb238d67ae91bab636b11896092a71234726a8d8c5eac91` | PASS | P7.8 | none |
| OPT-06 | R23 | translation revision changes and stale hydrate rejected | `d727e4cef0d0272cb98066fa749afc02ee697597d159e46331218563314bc9aa` | PASS | P7.8 | none |
| OPT-07 | R23 | read descriptors stay read-only without eager network | `d727e4cef0d0272cb98066fa749afc02ee697597d159e46331218563314bc9aa` | PASS | P7.8 | P8 invoke receipt future |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen optional bridge profile |
| `findings/P7-locale.md` | `9a34389149c33fbcf7827d5b58fba4c986b5ea75183061780adc9264778db2ff` | locale/translation adoption |
| `findings/P6-search-query.md` | `6f5f4f0977d62fc70a3696836940bbabc8185453e0bfdab6e6c08636e6b0b8ab` | search/1 producer receipt |
| `findings/P6-index-lifecycle.md` | `219011207a031727aea5b32ae9b27a04111fefe1d90a2813fd3fec2befc1d1f7` | index-lifecycle/1 producer receipt |
| `findings/P4-saved-views.md` | `53d3c4926476a09f87a118275bc2e86a7dd94c50b49e3237d4e4f85c77292415` | saved-view/1 label metadata source |
| `packages/intl/src/Contracts/AddressSuggestProvider.php` | `3ed491d848f92881542b82bb5d2cca4bd63e7254015a78ce532d875963d6c5df` | public address port |
| `packages/intl/src/Data/AddressQuery.php` | `56687fbbab1035ed202d416e274cfac53b6db9d2575b2d45dd1cebbf7fc2a662` | bounded address query DTO |
| `packages/intl/src/Internal/Address/FixtureAddressSuggestProvider.php` | `9f69daf427879c44be60009b57511111dadbf4c76924c0dee7912033d93d629f` | fixture driver A |
| `packages/intl/src/Internal/Address/MapAddressSuggestProvider.php` | `c77535c8065fee24a96df8cf9bdbc536225b72394b9e13cad05178890954d97b` | configured driver B |
| `packages/intl/tests/Address/AddressSuggestTest.php` | `a527a8479b72705d9bb238d67ae91bab636b11896092a71234726a8d8c5eac91` | OPT-01/04/05 proof |
| `packages/intl-search/src/Internal/LocalizedSearchSchema.php` | `6ee3e394053c4d09be34979a76a833908cd21108b333310d1419673705a942f5` | localized search projection |
| `packages/intl-search/src/Internal/SqlSavedViewLabelReader.php` | `babbed2522350c1d2e9959fb57951950edd0d40900d2499e3e4f7a9e50598c00` | saved-view label adapter |
| `packages/intl-search/tests/Unit/OptionalBridgesTest.php` | `d727e4cef0d0272cb98066fa749afc02ee697597d159e46331218563314bc9aa` | OPT-01/02/03/06/07 proof |
| `artifacts/qualification/p7/optional-qualification-receipt.md` | `8b23cc11d2b4811eb81f3227c83716ba8d43c2a9f3547a31040758f4fa81dc6b` | optional bridge qualification receipt |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| AddressSuggestProvider | G-API | `3ed491d848f92881542b82bb5d2cca4bd63e7254015a78ce532d875963d6c5df` | P7.8 | PASS | P7.9 installed seal |
| intl-optional-bridge/1 | proposal:G-API | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P7.8 | PASS | Q-P2-052 owner receipt before external driver adoption |
| corex/intl-search | G-API | `455d54f0718a35710ffa51a3b05c8d290871b6186a81b668729793d6ef248d24` | P7.8 | PASS | consumer enables explicitly |
