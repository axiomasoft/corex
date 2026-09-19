# Executor note — remote + CI deferred (D44/D45)

**До terminal `PLAN-CLOSE-CI` (после P11.7):**

| Запрещено | Разрешено (product) |
|---|---|
| `remaining-cohort-publish.py --write` | локальный код helpers/fixtures |
| mass `--reconcile` (18 repos) | `--preflight`, `--verify-pub-*`, `--build`, `--finalize` |
| `remaining-cohort-hosted.py` без `--finalize` | `remaining-cohort-hosted.py --finalize` (cached receipt) |
| `remaining-cohort-private.py` без `--finalize` | `remaining-cohort-private.py --finalize` (cached receipt) |
| `composer test:arch`, package-graph-gate | `validate-p9.py --stage branches|seal` |
| правки `.github/workflows/*` | unit tests без GitHub |
| `gh release` / split push / `gh workflow run` | |

**Флаг:** `scripts/.plan-close-deferred` — guard в `ci-deferred-guard.sh`, `plan_close_deferral.py`, `remaining-cohort-publish.py`, `remaining-cohort-hosted.py`, `remaining-cohort-private.py`.

## P9.9 rebaseline (Variant A) — жёсткий контракт

Executors закрывают P9.9 **только локальным product-кодом**. Скрипты, которые ходят в GitHub/CI (`gh`, hosted reconcile, remote publication), **не вызываются** — скрипт откажет с `DeferralDenied`.

Допустимые carriers для обновления findings/receipts:

```bash
python3 scripts/distribution/remaining-cohort-export.py          # cohort bytes (один раз)
python3 scripts/distribution/remaining-cohort-quality.py
python3 scripts/distribution/remaining-cohort-installed.py
python3 scripts/distribution/remaining-cohort-hosted.py --finalize
python3 scripts/distribution/remaining-cohort-private.py --finalize
python3 scripts/distribution/remaining-cohort-publish.py --build
python3 scripts/distribution/remaining-cohort-publish.py --finalize
python3 scripts/distribution/remaining-cohort-released.py
python3 plans/.../scripts/repair-p9-evidence-rows.py            # при необходимости
python3 plans/.../scripts/validate-p9.py --stage seal
```

**Снять только в `PLAN-CLOSE-CI`:** удалить флаг → full `--write` cohort → remote reconcile P9.8/P9.9 → CI green.

**P9.8–P11:** пишем код по пунктам; сеть/GitHub/CI не трогаем.
