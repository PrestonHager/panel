<?php

namespace Pterodactyl\TestPlugin;

use Pterodactyl\Plugins\PluginContext;
use Pterodactyl\Plugins\Contracts\PluginInterface;

class Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
    }
}
