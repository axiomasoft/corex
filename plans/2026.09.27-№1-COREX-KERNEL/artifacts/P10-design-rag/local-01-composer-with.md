# local-01 — Composer: временное ограничение версии без изменения манифеста

- Tool: installed primary tool, `composer update --help` · Composer version 2.8.2 2024-10-29 · Date: 2026-10-01
- Status: primary (вывод установленной программы).

## Output (verbatim, relevant lines)

```text
      --with=WITH                                      Temporary version constraint to add, e.g. foo/bar:1.0.0 or foo/bar=1.0.0 (multiple values allowed)
  php composer.phar update --with vendor/package:1.0.*
```

## Decision impact

D74 п.6: шаги CI «Constrain Laravel version» (`composer require "<package>:$LARAVEL_VERSION" --no-update`) заменяются
`composer update --with "<package>:$LARAVEL_VERSION"`: отслеживаемые `composer.json` не изменяются, тесты, сверяющие
манифест, видят его исходным. Ограничение пересекается с корневым; пустое пересечение (Pest `^4.7` при корневом
`^5.2`) таким способом не выражается — для него шаг `require --dev` остаётся (D42).
