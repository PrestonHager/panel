<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Illuminate\Http\Request;

class PluginController extends ClientApiController
{
    public function enabled(Request $request): array
    {
        $plugins = Plugin::query()->where('enabled', true)->get();
        $output = [];

        foreach ($plugins as $plugin) {
            if (!in_array(Permissions::UI_REGISTER, $plugin->permissions ?? [], true)) {
                continue;
            }

            $ui = $plugin->ui_config ?? [];
            if (empty($ui['server'])) {
                continue;
            }

            $output[] = [
                'id' => $plugin->id,
                'ui' => [
                    'server' => $ui['server'],
                ],
            ];
        }

        return ['data' => $output];
    }

    public function permissionsCatalog(): array
    {
        $plugins = Plugin::query()->where('enabled', true)->get();
        $catalog = [];

        foreach ($plugins as $plugin) {
            $clientPermissions = $plugin->client_permissions ?? [];
            if (!empty($clientPermissions)) {
                $catalog[$plugin->id] = $clientPermissions;
            }
        }

        return [
            'object' => 'plugin_client_permissions',
            'attributes' => [
                'plugins' => $catalog,
            ],
        ];
    }
}
