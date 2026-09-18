# P12A correction receipts

Append-only receipt carrier for corrective phase P12A (`decisions/D42-p12-post-audit.md`).

## P12A.1 — Снять `DESIGN_PENDING`: восстановить design binding плана

### Design gate до правки

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design
Task runtime check FAIL: DESIGN_PENDING: finish input changed: phases/P12/P12.md
exit=1
```

### Design gate после правки

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design
Design gate PASS: 12 detailed phases, Perplexity evidence and current sealed whole-plan review; runtime acceptance remains item-owned
exit=0
```

### Изменённые строки binding (`artifacts/design-finish.md`)

| Path | Sealed (до) | Actual (после) |
|:--|:--|:--|
| `phases/P12/P12.md` | `80512d422e05ef44caa8481d3dbadf44e3c0dd76b994b13848a62479b0668e32` | `8218c0595f4aafe91f3877ae43b6d74703488bd803f65c8d6aef4bc57ad76693` |
| `plan.md` | `c9e2962defdece0462c4eb69f54bf1406da9805883c42349e15e0d622739e1f7` | `72df7e8b5dc2144264ae5568d9ea9c1b7d362034d2095f9a30e0e7e0253f74fa` |
| `roadmap.md` | `f9bb98d223ac33d25483522d6b5176989757a4c3eb37b3eaee32cfdbf094d950` | `88e3061e5ac3ac6e459ea6bcfbb2f0e73dd075e621ef6b916aa0cefa40b1ec6d` |
| `scripts/validate-p12.py` | `1a295aad84c2441b4289070c8bc7e7de845de056fd33212394d7ee9f650ca120` | `a500887e2b23e04f677409960f335db8ceb2ce8519b49a46fd54121e86b47663` |
| `scripts/task-check.py` | `29f4ee64603343ff94319925f0bb5d3f56d3ddebb7e662349246d995ae885ee3` | `dc478e5f0bdb90f0a108fba0bed5ba0374d96c30cf73ecc143c211a3aefa55b5` |

Причина рассинхронизации `phases/P12/P12.md`: файл перезаписан при закрытии P12.9; binding не обновлён (дефект R4). Расширение `scripts/task-check.py::design_inputs` на фазу P12A выполнено тем же проходом регенерации.

### Добавленные строки binding (фаза P12A)

| Path | SHA256 |
|:--|:--|
| `phases/P12A/P12A.md` | `138870341402d0c6c76ef3970f29f1f4b382a9d0e1d6810535d18f734492ded0` |
| `phases/P12A/P12A.1.md` | `75e8c96ca8c950251b3720c3ada9dbd80b911557c027d483a461e3a5e2d02ceb` |
| `phases/P12A/P12A.2.md` | `c17cc4d13b357d0f02e5d81c965f39459bc28fbbde24ee319178bf0197659329` |
| `phases/P12A/P12A.3.md` | `05252fbbc0fc0fa6cccee8e48e917d8c08fb99d136023805789c3b733198f811` |
| `phases/P12A/P12A.4.md` | `b5cefa2ade7c2753996daada3782108c7531196b5cc0f62a819f68f2d055ab97` |
| `phases/P12A/P12A.5.md` | `a98904c87a25c23f4af52a24981e337253156cfded1d7144c043efe977acd996` |
| `phases/P12A/P12A.6.md` | `f020c91a3208a0e36b1d18c891c215d912a74ca71a2859d88cc95479b0d7572b` |
| `findings/P12-post-close-audit.md` | `71a7601dfa2f0ad7c63bb50427ea8a542686bb6a67806a6496940d7520d771a5` |
| `decisions/D42-p12-post-audit.md` | `80d6d035d89435f2c7547f8e4dd793cc70dd64e4c04d88af68b4f8e142496ba5` |

### Scope check

```text
$ git diff --stat -- plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/artifacts/design-finish.md \
    plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py \
    plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/findings/P12A-correction-receipts.md
```

Product paths (`packages/**`, корневой `scripts/**`) этим item не изменялись.

write-site: pre_mutation: allow · pre_final: allow

## P12A.2 — Привести `composer lint:check` к зелёному без смены поведения

### Lint до правки (14 product-файлов R2 + gitignored delivery)

```text
$ composer lint:check
{"tool":"pint","result":"fail","files":[…14 product paths…, "delivery/utility-install-qualification-20260915/fixtures/probe.php"]}
exit=1
```

### Lint после правки

```text
$ composer lint:check
{"tool":"pint","result":"passed"}
exit=0
```

Per-package (все exit 0): `intl`, `transfer`, `search`, `search-intl`, `attributes`, `tasks-workflow`.

### Прочие карьеры

| Carrier | Result |
|:--|:--|
| `composer test:packages` | exit 0 (472 passed, 7 skipped) |
| `composer analyse` | 0 errors |
| `composer test:arch` | 7 passed |
| `php scripts/package-graph-gate.php` | OK |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `pint.json` | без изменений |

### Примечание по delivery

После Pint по четырнадцати файлам R2 корневой `lint:check` оставался красным только на
`delivery/utility-install-qualification-20260915/fixtures/probe.php` (gitignored, вне перечня R2;
на HEAD тот же единственный fail). Для carrier exit 0 применён Pint только к этому fixture;
семантика не менялась.

### §10-контур P12A

`composer lint:check` из корня отсутствовал в §10-контуре P12.9
(`findings/P12-rebaseline-receipts.md:290-299`); вносится в контур P12A как обязательный карьер.

write-site: pre_mutation: allow · pre_final: allow

## P12A.2 — Привести `composer lint:check` к зелёному без смены поведения

### Lint до правки (14 product-файлов R2 + gitignored delivery)

```text
$ composer lint:check
{"tool":"pint","result":"fail","files":[…14 product paths…, "delivery/utility-install-qualification-20260915/fixtures/probe.php"]}
exit=1
```

### Lint после правки

```text
$ composer lint:check
{"tool":"pint","result":"passed"}
exit=0
```

Per-package (все exit 0): `intl`, `transfer`, `search`, `search-intl`, `attributes`, `tasks-workflow`.

### Прочие карьеры

| Carrier | Result |
|:--|:--|
| `composer test:packages` | exit 0 (472 passed, 7 skipped) |
| `composer analyse` | 0 errors |
| `composer test:arch` | 7 passed |
| `php scripts/package-graph-gate.php` | OK |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `pint.json` | без изменений |

### Примечание по delivery

После Pint по четырнадцати файлам R2 корневой `lint:check` оставался красным только на
`delivery/utility-install-qualification-20260915/fixtures/probe.php` (gitignored, вне перечня R2;
на HEAD тот же единственный fail). Для carrier exit 0 применён Pint только к этому fixture;
семантика не менялась.

### §10-контур P12A

`composer lint:check` из корня отсутствовал в §10-контуре P12.9
(`findings/P12-rebaseline-receipts.md:290-299`); вносится в контур P12A как обязательный карьер.

write-site: pre_mutation: allow · pre_final: allow
