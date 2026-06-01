# Event hooks

Declare hooks in `plugin.json` under `hooks`. Requires `events.subscribe` permission.

| Hook | Event | Typical use |
|------|-------|-------------|
| `server.creating` | Before server is persisted | Validation |
| `server.created` | After server record created | Provision DNS, notify external systems |
| `server.installed` | After daemon reports install complete | Finalize DNS when IP is known |
| `server.deleting` | Before deletion | Remove external resources |
| `server.deleted` | After deletion | Cleanup metadata |

## Example: DNS on install

```php
public function handle(PluginContext $context, Installed $event): void
{
    $network = $context->servers()->getNetworkSummary($event->server->id);
    $primary = collect($network->allocations)->firstWhere('isPrimary', true);

    if (!$primary) {
        return;
    }

    $response = $context->http()->post('https://api.example.com/records', [
        'json' => ['ip' => $primary->ip, 'name' => $network->server->name],
    ]);

    $context->data()->set('server', $network->server->id, 'dns_record', [
        'status' => $response['status'],
    ]);
}
```

## Long-running work

Hooks run synchronously. Dispatch a Laravel job from the listener for slow HTTP calls:

```php
ProvisionDnsJob::dispatch($event->server->id);
```

Job classes should live in the plugin `src/` tree and remain autoloaded while the plugin is enabled.
