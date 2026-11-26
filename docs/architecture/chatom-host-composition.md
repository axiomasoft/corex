# Using Chatom in a CoreX application

CoreX delivers domain events through the outbox. Chatom owns user messages, channel membership, spaces and channels. The host installs `axiomasoft/chatom` and registers its module with Packman. The application owns any references that connect a domain entity to a conversation, space or channel. Use `Chatom\Spaces\Contracts\Spaces`, `Chatom\Channels\Contracts\Channels` and `Chatom\Messages\Contracts\Messages` to access Chatom data.

The host adapts its established tenant identity to `Chatom\Spaces\Contracts\TenantContext`. This lookup must perform no I/O and must never initialize tenancy or switch connections.

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

Register the adapter in the host configuration.

```php
'chatom.tenancy.context' => ChatTenantContext::class,
```

`AccountIsolationMapper::context()` must project previously verified tenant state. Chatom uses the account key to isolate caches, locks, realtime events and queued jobs. The application policy and Chatom membership determine workspace visibility and message read access.

Initialize tenancy before the HTTP request or queue worker reaches Chatom. The same runtime owner closes the scope on completion. The module reads the active account through the adapter, independently of the request or queue transport.
