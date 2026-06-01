<?php

namespace Com\Example\MyPlugin;

use Pterodactyl\Plugins\PluginContext;
use Pterodactyl\Plugins\Contracts\PluginInterface;

class Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        // Optional startup logic (runs when the plugin is enabled and the panel boots).
    }
}
