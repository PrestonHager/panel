<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Plugins\Permissions;

class PluginEventDispatcher
{
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginContextFactory $contextFactory,
    ) {
    }

    public function dispatch(string $hook, object $event): void
    {
        if (!HookMap::isValidHook($hook)) {
            return;
        }

        foreach ($this->registry->entries() as $pluginId => $entry) {
            $permissions = $entry['plugin']->permissions ?? [];
            if (!in_array(Permissions::EVENTS_SUBSCRIBE, $permissions, true)) {
                continue;
            }

            $listenerClass = $entry['manifest']->hooks[$hook] ?? null;
            if (!is_string($listenerClass) || $listenerClass === '') {
                continue;
            }

            if (!class_exists($listenerClass)) {
                continue;
            }

            $listener = new PluginHookListener($pluginId, $listenerClass, $this->contextFactory);
            $listener($event);
        }
    }
}
