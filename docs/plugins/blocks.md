# Plugin blocks

Plugins may register **block types** and **keybind actions** for layout tools such as the Panel Builder plugin.

## Manifest

```json
{
  "permissions": ["ui.blocks.register"],
  "ui": {
    "blocks": [
      {
        "id": "com.example.widget",
        "label": "Example Widget",
        "bundle": "assets/widget-block.js",
        "surfaces": ["client"]
      }
    ],
    "keybinds": [
      {
        "id": "example.action",
        "label": "Run example action",
        "default": "g e",
        "surfaces": ["client"]
      }
    ]
  }
}
```

## Catalog API

`GET /api/client/plugins/blocks` returns merged `blocks` and `keybinds` from all enabled plugins with `ui.blocks.register`.

## Embedding other plugins

Layout runtimes should mount plugin bundles in isolated `#plugin-root-{pluginId}` containers using the standard bundle contract. Do **not** replace or override existing `ui.server` tab routes.

Third-party plugins (e.g. DNS) continue to work unchanged; optional `ui.blocks` entries expose embeddable widgets only.
