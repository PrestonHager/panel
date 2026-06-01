<?php

namespace Com\Example\MyPlugin\Listeners;

use Pterodactyl\Plugins\PluginContext;
use Pterodactyl\Events\Server\Created;

class OnServerCreated
{
    public function handle(PluginContext $context, Created $event): void
    {
        $server = $event->server;

        $context->activity()->log('server-created', [
            'server_id' => $server->id,
            'server_uuid' => $server->uuid,
        ]);

        $context->data()->set('server', $server->id, 'provisioned', [
            'at' => now()->toIso8601String(),
        ]);
    }
}
