# Configuration

## Publishing

| Package | Publish tag | Config key | Environment prefix |
| --- | --- | --- | --- |
| `axiomasoft/corex` | `corex-config` | `corex` | `COREX_*` |
| Packman Modules | `packman-modules-config` | `modules` | `PACKMAN_MODULES_*` |
| Other CoreX packages | see the package README | `corex-<package>` | `COREX_<PACKAGE>_*` |

```bash
php artisan vendor:publish --tag=corex-config
```

Published files are optional: the package config files ship the defaults, and
packages read their settings through typed options objects. Configuration is
compatible with `php artisan config:cache`; `composer config-cache-check`
proves it.

## Reference

[`architecture/configuration.md`](architecture/configuration.md) lists every
key, its type, default and owning package. It is generated from the package
config files:

```bash
php scripts/configuration-reference.php --write   # regenerate
composer configuration-reference                  # verify (CI)
```

Never edit the generated file by hand. The rules for adding a key (naming,
classification, secrets) are in the
[configuration policy](architecture/config-policy.md).

## Secrets

Secret values come from the environment and are masked as `***` in the
generated reference.
