<?php

namespace Pterodactyl\TestPlugin\Listeners;

use Pterodactyl\Plugins\PluginContext;
use Pterodactyl\Events\Server\Created;

class OnServerCreated
{
    public function handle(PluginContext $context, Created $event): void
    {
        $context->data()->set('server', $event->server->id, 'hook_ran', [
            'ran' => true,
        ]);
    }
}
