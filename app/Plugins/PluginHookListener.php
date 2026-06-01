<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\PluginContextFactory;

class PluginHookListener
{
    public function __construct(
        private readonly string $pluginId,
        private readonly string $listenerClass,
        private readonly PluginContextFactory $contextFactory,
    ) {
    }

    public function __invoke(object $event): void
    {
        $plugin = Plugin::query()->find($this->pluginId);
        if (is_null($plugin) || !$plugin->enabled) {
            return;
        }

        if (!class_exists($this->listenerClass)) {
            return;
        }

        $listener = new $this->listenerClass();
        $context = $this->contextFactory->make($plugin);

        if ($listener instanceof \Closure) {
            $listener($context, $event);

            return;
        }

        if (is_callable($listener)) {
            $listener($context, $event);

            return;
        }

        if (method_exists($listener, 'handle')) {
            $listener->handle($context, $event);
        }
    }
}
