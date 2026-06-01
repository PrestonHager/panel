# Plugin HTTP API

Plugin-owned routes are served under:

```
/api/plugins/{plugin_id}/...
```

## Manifest

```json
{
  "permissions": ["api.serve", "..."],
  "clientPermissions": {
    "records.read": "View records"
  },
  "api": {
    "routes": [
      {
        "method": "GET",
        "path": "/servers/{server}/records",
        "handler": "Com\\Example\\Plugin\\Http\\RecordsController@index",
        "permission": "records.read"
      }
    ]
  }
}
```

## Handlers

Handlers receive `(PluginContext $context, PluginHttpRequest $request)` and return `array` or `PluginHttpResponse`.

- `{server}` is the server UUID
- Use `$context->servers()->findByUuid(...)` for server metadata
- Never return secrets in JSON responses

## Authentication

Routes use the same client API authentication as `/api/client` (session or client API key).

Middleware enforces:

1. Plugin is enabled
2. Optional plugin client permission from the route definition
3. Server access when `{server}` is present
