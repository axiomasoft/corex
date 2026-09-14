# CRM parity — ссылка на поставку

Канонический durable receipt:
[/home/vostrikov/projects/packages/corex/delivery/crm-parity-20260914/receipt.md](/home/vostrikov/projects/packages/corex/delivery/crm-parity-20260914/receipt.md).

Реализация, logs и artifacts теперь в единственной локальной ветке `main`, workspace
`/home/vostrikov/projects/packages/corex`. Immutable source candidate commit
`fb156f5897c1a703c57f59b37719e1efb1d822ff` сохранён в истории main. Дополнительный
worktree удалён. Чужие dirty core/modules/tenancy/plans сохранены незакоммиченными.

CRM tests64/64, coverage96.9%, main полный suite217/217,coverage88.8%, installed PostgreSQL scenario2/2. Local artifact
`flex/crm:dev-crm-parity-fb156f5897c1` доступен для квалификации. Release не выполнен;
app install NO-GO по dependency/full-schema gates, подробно в receipt.
