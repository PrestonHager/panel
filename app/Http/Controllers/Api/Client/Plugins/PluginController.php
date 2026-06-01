<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Plugins\PluginUiConfigService;

class PluginController extends ClientApiController
{
    public function __construct(
        private readonly PluginUiConfigService $pluginUiConfigService,
    ) {
        parent::__construct();
    }

    public function enabled(): array
    {
        return ['data' => $this->pluginUiConfigService->enabledClientServerPlugins()];
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
