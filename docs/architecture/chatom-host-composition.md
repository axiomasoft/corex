# Chatom в приложении CoreX

CoreX messaging доставляет доменные события через outbox. `axiomasoft/chatom` хранит
пользовательские сообщения, участников, пространства и каналы. Замены одного другим
нет; отдельный пустой пакет `corex-chatom` не нужен.

Приложение устанавливает Chatom и регистрирует свой модуль через Packman. Оно связывает
прикладную сущность с conversation/space/channel через публичные `Chatom\Spaces\Contracts\Spaces`,
`Chatom\Channels\Contracts\Channels` и `Chatom\Messages\Contracts\Messages`. Ссылки на
conversation принадлежат приложению; тело сообщений и членство в каналах — Chatom.

Chatom принимает уже установленный контекст через
`Chatom\Spaces\Contracts\TenantContext`. Это чтение текущей идентичности без I/O,
инициализации tenant или переключения соединения. Пример класса приложения:

```php
use Chatom\Spaces\Contracts\TenantContext;
use Chatom\Spaces\Data\TenantKey;
use CoreX\Context\Contracts\TenancyManager;

final readonly class ChatTenantContext implements TenantContext
{
    public function __construct(private TenancyManager $tenancy) {}

    public function current(): ?TenantKey
    {
        $context = $this->tenancy->context();

        return $context === null ? null : TenantKey::from($context->account->id);
    }
}
```

Класс задаётся в конфигурации приложения:

```php
'chatom.tenancy.context' => ChatTenantContext::class,
```

Host `AccountIsolationMapper::context()` должен быть чистой проекцией уже проверенного
состояния Tenant, иначе этот адаптер нарушит запрет Chatom на I/O. Workspace visibility
определяется политикой приложения и членством Chatom, а не подменой account tenant key.
Chatom использует этот ключ для cache/lock/realtime/queued-job изоляции; сам ключ не
авторизует чтение сообщения и не устанавливает физический tenant.

Входящий HTTP-запрос и очередь сначала проходят общую инициализацию Tenant. Затем
Chatom получает тот же активный account. По завершении область закрывает единственный
владелец runtime. Модуль не вызывает `initialize()` из `current()` и не читает
внутренние таблицы Chatom. Публичные события и `SearchProjectionSink` используются для
проекций поиска и уведомлений с идемпотентностью на стороне получателя.
