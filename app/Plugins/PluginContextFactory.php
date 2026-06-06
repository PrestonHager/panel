<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\Accessors\ActivityAccessor;
use Pterodactyl\Plugins\Accessors\ConfigAccessor;
use Pterodactyl\Plugins\Accessors\HttpClientAccessor;
use Pterodactyl\Plugins\Accessors\PluginDataAccessor;
use Pterodactyl\Plugins\Accessors\ServerAccessor;
use Pterodactyl\Plugins\Accessors\SettingsAccessor;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class PluginContextFactory
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
    ) {
    }

    public function make(Plugin $plugin): PluginContext
    {
        $gate = new PermissionGate($plugin->id, $plugin->effectivePermissions());

        return new PluginContext(
            pluginId: $plugin->id,
            gate: $gate,
            settings: new SettingsAccessor($plugin->id, $gate, $this->settings),
            config: new ConfigAccessor($gate, $plugin->config ?? []),
            servers: new ServerAccessor($gate),
            data: new PluginDataAccessor($plugin->id, $gate),
            http: new HttpClientAccessor(
                $gate,
                config('pterodactyl.plugins.http.allowed_hosts', []),
                $plugin->approved_http_hosts ?? [],
                (int) config('pterodactyl.plugins.http.timeout', 30),
            ),
            activity: new ActivityAccessor($plugin->id, $gate),
        );
    }
}
