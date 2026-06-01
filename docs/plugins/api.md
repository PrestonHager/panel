# Plugin API

Plugins receive a `PluginContext` in `register()` and in hook listeners.

## Entry point

```php
use Pterodactyl\Plugins\PluginContext;
use Pterodactyl\Plugins\Contracts\PluginInterface;

class Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        // Runs when the plugin is enabled and the panel boots.
    }
}
```

## Hook listeners

```php
public function handle(PluginContext $context, \Pterodactyl\Events\Server\Created $event): void
{
    $summary = $context->servers()->find($event->server->id);
}
```

Listeners may also be invokable classes: `__invoke(PluginContext $context, object $event)`.

## Accessors

### `$context->config()`

Requires `config.read`. Reads admin-configured encrypted JSON from **Plugins → Settings** (not the panel settings table).

- `get(string $key, mixed $default = null)`
- `all(): array` (never expose via HTTP)

### `$context->settings()`

- `get(string $key, mixed $default = null)`
- `set(string $key, ?string $value)`
- `forget(string $key)`

Keys are automatically prefixed with `plugins.{plugin_id}.`.

### `$context->servers()`

- `find(int $serverId): ServerSummary`
- `findByUuid(string $uuid): ServerSummary`
- `getNetworkSummary(int $serverId): NetworkSummary` (requires `allocation.read`)

### `$context->data()`

Plugin-owned metadata (requires `server.metadata.write` for writes):

- `get(string $subjectType, int $subjectId, string $key, mixed $default = null)`
- `set(string $subjectType, int $subjectId, string $key, array $value)`
- `delete(string $subjectType, int $subjectId, string $key)`

v1 supports `subject_type = server` only.

### `$context->http()`

- `get(string $url, array $options = []): array`
- `post(string $url, array $options = []): array`
- `request(string $method, string $url, array $options = []): array`

Returns `['status' => int, 'body' => string, 'headers' => array]`.

### `$context->activity()`

- `log(string $event, array $properties = [])`

Writes `plugin:{plugin_id}:{event}` to the activity log.

## Errors

- `PluginPermissionDeniedException` — missing permission
- `PluginException` — general plugin errors (invalid keys, missing server, blocked HTTP host)
