# Plugin client permissions

Plugin client permissions are **declared per plugin** in `plugin.json` → `clientPermissions`. They are separate from core panel permissions (`file.read`, etc.).

## Manifest example

```json
{
  "clientPermissions": {
    "records.read": "View DNS records",
    "records.create": "Create DNS records"
  }
}
```

## Subuser grants

Granted per subuser in `subuser_plugin_permissions`. Owners and root admins receive all client permissions implicitly.

The subuser editor shows a collapsible section per enabled plugin.

## API enforcement

Plugin HTTP routes may declare `"permission": "records.read"`. The panel calls `PluginClientPermissionGate` before invoking the handler.

## Frontend

Server API meta includes `plugin_permissions`:

```json
{
  "com.example.dns": ["records.read"]
}
```

Use `usePluginPermissions(pluginId, 'records.read')` in React.
