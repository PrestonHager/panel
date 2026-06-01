<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Plugins\Accessors\ActivityAccessor;
use Pterodactyl\Plugins\Accessors\ConfigAccessor;
use Pterodactyl\Plugins\Accessors\HttpClientAccessor;
use Pterodactyl\Plugins\Accessors\PluginDataAccessor;
use Pterodactyl\Plugins\Accessors\ServerAccessor;
use Pterodactyl\Plugins\Accessors\SettingsAccessor;

class PluginContext
{
    public function __construct(
        private readonly string $pluginId,
        private readonly PermissionGate $gate,
        private readonly SettingsAccessor $settings,
        private readonly ConfigAccessor $config,
        private readonly ServerAccessor $servers,
        private readonly PluginDataAccessor $data,
        private readonly HttpClientAccessor $http,
        private readonly ActivityAccessor $activity,
    ) {
    }

    public function id(): string
    {
        return $this->pluginId;
    }

    public function gate(): PermissionGate
    {
        return $this->gate;
    }

    public function settings(): SettingsAccessor
    {
        return $this->settings;
    }

    public function config(): ConfigAccessor
    {
        return $this->config;
    }

    public function servers(): ServerAccessor
    {
        return $this->servers;
    }

    public function data(): PluginDataAccessor
    {
        return $this->data;
    }

    public function http(): HttpClientAccessor
    {
        return $this->http;
    }

    public function activity(): ActivityAccessor
    {
        return $this->activity;
    }
}
