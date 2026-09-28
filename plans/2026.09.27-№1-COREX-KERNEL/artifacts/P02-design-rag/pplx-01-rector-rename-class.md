# pplx-01 — Rector RenameClassRector scope

- Tool: perplexity-web `search` · Date: 2026-09-28 · Used by: D44 (E1)
- Query: «Rector PHP 2.x RenameClassRector: when an old class is renamed to a class in a different namespace, does Rector change the namespace declaration of the file declaring the class, update docblock @param/@var types, and update string literals containing the class name? Does it move the file? Behavior of withImportNames with renamed classes.»

## Answer (verbatim)

Коротко: в Rector 2.x RenameClassRector переименовывает ссылки на класс, но не переносит само объявление класса в новый namespace и не перемещает файл. Docblock-типы он обновляет; обычные строковые литералы с именем класса — нет.

## Что меняется

- Ссылки на класс в PHP-коде: импорты, type hints, new, instanceof и другие распознаваемые ссылки на имя класса заменяются на целевой класс.
- Типы в PHPDoc: @param, @var и аналогичные type annotations обрабатываются как типы, поэтому ссылки на переименованный класс обновляются. Обработка также поддерживает некоторые алиасы в docblock.
- Строковые литералы: обычные строки вроде 'Old\\Namespace\\Thing' не являются ссылками на тип и не меняются этим правилом. Для текстовых значений есть отдельный RenameStringRector; не рассчитывайте, что он автоматически распознает все строки как имена классов.

## Объявление и файл

Если файл содержит namespace Old\Namespace; class Thing, RenameClassRector сам не заменит объявление на namespace New\Namespace; и не переместит/переименует файл. Правило предназначено для обновления ссылок, а не для миграции объявления класса или структуры каталогов.

## withImportNames()

Эта настройка влияет на форматирование импортов, а не на область действия RenameClassRector: Rector может вывести новое имя через use New\Namespace\Thing; и короткое Thing, либо оставить FQN — в зависимости от настроек импорта. Она не заставляет Rector менять namespace самого объявленного класса, редактировать строковые литералы или перемещать файл.
Практически: после замены на класс из другого namespace ссылки в потребителях могут получить новый use при withImportNames(), но файл с исходным объявлением нужно мигрировать отдельно.

## Sources

1. https://getrector.com/rule-detail/rename-class-rector
2. https://github.com/rectorphp/rector/blob/main/docs/rector_rules_overview.md
3. https://github.com/rectorphp/rector/discussions/8290
