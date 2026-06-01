<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Facades\Activity;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;

class ActivityAccessor
{
    public function __construct(
        private readonly string $pluginId,
        private readonly PermissionGate $gate,
    ) {
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function log(string $event, array $properties = []): void
    {
        $this->gate->authorize(Permissions::ACTIVITY_LOG);

        Activity::event('plugin:' . $this->pluginId . ':' . $event)
            ->property('plugin_id', $this->pluginId)
            ->property('properties', $properties)
            ->log();
    }
}
