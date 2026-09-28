# http-02 — GitHub releases (CI-инструменты) и политика версий PostgreSQL

- **Дата:** 2026-09-28
- **Инструмент:** `curl https://api.github.com/repos/<owner>/<repo>/releases/latest`; `curl https://www.postgresql.org/support/versioning/` (таблица разобрана Python)
- **Запрос:** последние релизы `zizmorcore/zizmor`, `zizmorcore/zizmor-action`, `suzuki-shunsuke/pinact`,
  `shivammathur/setup-php`, `actions/checkout`; поддерживаемые мажоры PostgreSQL.

## Ответ (дословный вывод)

```text
zizmorcore/zizmor v1.30.1 2026-09-09
suzuki-shunsuke/pinact v5.0.0 2026-09-12
shivammathur/setup-php 2.37.2 2026-06-08
actions/checkout v7.0.1 2026-07-20
zizmorcore/zizmor-action v0.6.4 2026-09-09

['Version', 'Current minor', 'Supported', 'First Release', 'Final Release']
['18', '18.6', 'Yes', 'September 25, 2025', 'November 14, 2030']
['17', '17.11', 'Yes', 'September 26, 2024', 'November 8, 2029']
['16', '16.15', 'Yes', 'September 14, 2023', 'November 9, 2028']
['15', '15.19', 'Yes', 'October 13, 2022', 'November 11, 2027']
['14', '14.24', 'Yes', 'September 30, 2021', 'November 12, 2026']
['13', '13.23', 'No', 'September 24, 2020', 'November 13, 2025']
['12', '12.22', 'No', 'October 3, 2019', 'November 21, 2024']
['11', '11.22', 'No', 'October 18, 2018', 'November 9, 2023']
```

## Источники

- https://api.github.com/repos/zizmorcore/zizmor/releases/latest
- https://api.github.com/repos/zizmorcore/zizmor-action/releases/latest
- https://api.github.com/repos/suzuki-shunsuke/pinact/releases/latest
- https://api.github.com/repos/shivammathur/setup-php/releases/latest
- https://api.github.com/repos/actions/checkout/releases/latest
- https://www.postgresql.org/support/versioning/
