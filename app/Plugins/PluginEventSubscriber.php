<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Events\Server\Created;
use Pterodactyl\Events\Server\Creating;
use Pterodactyl\Events\Server\Deleted;
use Pterodactyl\Events\Server\Deleting;
use Pterodactyl\Events\Server\Installed;
use Illuminate\Contracts\Events\Dispatcher;
use Pterodactyl\Extensions\Illuminate\Events\Contracts\SubscribesToEvents;

class PluginEventSubscriber implements SubscribesToEvents
{
    public function __construct(
        private readonly PluginEventDispatcher $dispatcher,
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Creating::class, fn (Creating $event) => $this->dispatcher->dispatch('server.creating', $event));
        $events->listen(Created::class, fn (Created $event) => $this->dispatcher->dispatch('server.created', $event));
        $events->listen(Installed::class, fn (Installed $event) => $this->dispatcher->dispatch('server.installed', $event));
        $events->listen(Deleting::class, fn (Deleting $event) => $this->dispatcher->dispatch('server.deleting', $event));
        $events->listen(Deleted::class, fn (Deleted $event) => $this->dispatcher->dispatch('server.deleted', $event));
    }
}
