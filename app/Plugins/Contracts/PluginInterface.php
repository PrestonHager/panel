<?php

namespace Pterodactyl\Plugins\Contracts;

use Pterodactyl\Plugins\PluginContext;

interface PluginInterface
{
    public function register(PluginContext $context): void;
}
